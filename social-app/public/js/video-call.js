import {
	doc,
	setDoc,
	getDoc,
	addDoc,
	collection,
	onSnapshot,
	serverTimestamp,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js';

export function createVideoCallFeature({ state, ui, startConversationByUserId, toast }) {
	let eventsBound = false;
	let callTimeoutHandle = null;

	if (!state.call) {
		state.call = {
			active: false,
			role: null,
			conversationId: null,
			peerId: 0,
			peerName: '',
			pc: null,
			localStream: null,
			remoteStream: null,
			callDocUnsub: null,
			incomingConversationId: null,
			currentCallId: null,
			incomingData: null,
			remoteCandidatesSeen: 0,
			lastVideoCallStatus: '',
			ringtoneInterval: null,
			ringtoneAudioContext: null,
			sentOutcomeKeys: new Set(),
		};
	}

	// Hide global call notification when chat page loads
	function hideGlobalCallNotification() {
		const globalCard = document.getElementById('globalIncomingCallCard');
		if (globalCard) {
			globalCard.classList.add('d-none');
		}
	}

	function getConversationDocRef(conversationId) {
		return doc(state.db, 'conversations', String(conversationId));
	}

	function getVideoCallData(data) {
		return data?.videoCall || null;
	}

	function isCallTerminal(status) {
		return ['ended', 'rejected', 'cancelled', 'busy', 'no_answer'].includes(String(status || ''));
	}

	function buildCallOutcomeText(status) {
		const normalized = String(status || '');
		const map = {
			ended: 'Cuộc gọi đã kết thúc.',
			rejected: 'Người nhận đã từ chối cuộc gọi.',
			no_answer: 'Người nhận không trả lời.',
			cancelled: 'Cuộc gọi đã bị hủy.',
			busy: 'Người nhận đang bận.',
		};
		return map[normalized] || '';
	}

	async function pushCallOutcomeMessage(conversationId, status, callCreatedAt = null) {
		if (!conversationId || !state.db) return;

		const text = buildCallOutcomeText(status);
		if (!text) return;

		if (!state.call.sentOutcomeKeys) {
			state.call.sentOutcomeKeys = new Set();
		}

		const key = `${String(conversationId)}:${String(callCreatedAt || 'na')}:${String(status)}`;
		if (state.call.sentOutcomeKeys.has(key)) {
			return;
		}
		state.call.sentOutcomeKeys.add(key);

		if (state.call.sentOutcomeKeys.size > 120) {
			state.call.sentOutcomeKeys.clear();
			state.call.sentOutcomeKeys.add(key);
		}

		await addDoc(collection(state.db, 'conversations', String(conversationId), 'messages'), {
			senderId: String(state.me.id),
			type: 'text',
			text,
			createdAt: serverTimestamp(),
		}).catch(() => {});

		await setDoc(getConversationDocRef(conversationId), {
			lastMessageText: text,
			lastMessageType: 'text',
			lastSenderId: Number(state.me.id),
			updatedAt: serverTimestamp(),
		}, { merge: true }).catch(() => {});
	}

	function clearCallTimeout() {
		if (callTimeoutHandle) {
			window.clearTimeout(callTimeoutHandle);
			callTimeoutHandle = null;
		}
	}

	function setupCallTimeout() {
		clearCallTimeout();
		// 60 second timeout for ringing phase
		callTimeoutHandle = window.setTimeout(() => {
			if (state.call.active && state.call.lastVideoCallState?.status === 'ringing') {
				console.warn('Call ringing timeout - no answer after 60 seconds');
				endActiveCall('no_answer').catch(() => {});
			}
		}, 60_000);
	}

	function getVideoCallSupportIssue() {
		if (!window.isSecureContext) {
			return 'Gọi video cần HTTPS hoặc localhost. Trang hiện tại chưa ở secure context.';
		}

		if (typeof window.RTCPeerConnection === 'undefined') {
			return 'Trình duyệt này chưa hỗ trợ WebRTC.';
		}

		if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
			return 'Trình duyệt chưa hỗ trợ truy cập camera/micro.';
		}

		return '';
	}

	function formatGetUserMediaError(error) {
		const name = String(error?.name || '');
		if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
			return 'Bạn chưa cấp quyền camera/micro cho trang này.';
		}
		if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
			return 'Không tìm thấy camera hoặc micro trên thiết bị.';
		}
		if (name === 'NotReadableError' || name === 'TrackStartError') {
			return 'Camera/micro đang được ứng dụng khác sử dụng.';
		}
		if (name === 'OverconstrainedError') {
			return 'Thiết bị không hỗ trợ cấu hình camera/micro hiện tại.';
		}
		return 'Không thể mở camera/micro để bắt đầu cuộc gọi.';
	}

	function showIncomingCallCard(callerName) {
		if (!ui.incomingCallCard) return;
		if (ui.incomingCallText) {
			ui.incomingCallText.textContent = `${callerName} đang gọi video cho bạn.`;
		}
		ui.incomingCallCard.classList.remove('d-none');
		
		// Hide global notification when showing chat-specific one
		hideGlobalCallNotification();
		startIncomingRingtone();
	}

	function hideIncomingCallCard() {
		stopIncomingRingtone();
		if (!ui.incomingCallCard) return;
		ui.incomingCallCard.classList.add('d-none');
	}

	function playRingtoneBurst() {
		const AudioContextClass = window.AudioContext || window.webkitAudioContext;
		if (!AudioContextClass) return;

		if (!state.call.ringtoneAudioContext) {
			state.call.ringtoneAudioContext = new AudioContextClass();
		}

		const ctx = state.call.ringtoneAudioContext;
		if (!ctx) return;

		if (ctx.state === 'suspended') {
			ctx.resume().catch(() => {});
		}

		const scheduleTone = (offset, duration = 0.16, frequency = 880) => {
			const now = ctx.currentTime;
			const startAt = now + offset;
			const stopAt = startAt + duration;

			const oscillator = ctx.createOscillator();
			const gainNode = ctx.createGain();

			oscillator.type = 'sine';
			oscillator.frequency.setValueAtTime(frequency, startAt);

			gainNode.gain.setValueAtTime(0.0001, startAt);
			gainNode.gain.exponentialRampToValueAtTime(0.08, startAt + 0.025);
			gainNode.gain.exponentialRampToValueAtTime(0.0001, stopAt);

			oscillator.connect(gainNode);
			gainNode.connect(ctx.destination);

			oscillator.start(startAt);
			oscillator.stop(stopAt + 0.03);
		};

		scheduleTone(0, 0.15, 880);
		scheduleTone(0.24, 0.15, 988);
	}

	function startIncomingRingtone() {
		if (state.call.ringtoneInterval) return;

		playRingtoneBurst();
		state.call.ringtoneInterval = window.setInterval(() => {
			playRingtoneBurst();
		}, 1500);
	}

	function stopIncomingRingtone() {
		if (state.call.ringtoneInterval) {
			window.clearInterval(state.call.ringtoneInterval);
			state.call.ringtoneInterval = null;
		}

		const ctx = state.call.ringtoneAudioContext;
		if (ctx && ctx.state === 'running') {
			ctx.suspend().catch(() => {});
		}
	}

	function setCallOverlayVisible(show) {
		if (!ui.callOverlay) return;
		const isCurrentlyHidden = ui.callOverlay.classList.contains('d-none');
		console.log('[VIDEO_CALL] setCallOverlayVisible:', show, '| currently hidden:', isCurrentlyHidden);
		ui.callOverlay.classList.toggle('d-none', !show);
		console.log('[VIDEO_CALL] After toggle, hidden:', ui.callOverlay.classList.contains('d-none'));
	}

	function updateCallStatusLabel(message) {
		if (!ui.callStatus) return;
		ui.callStatus.textContent = String(message || '');
	}

	function renderLocalVideo() {
		if (!ui.localVideo) return;
		ui.localVideo.srcObject = state.call.localStream || null;
	}

	function renderRemoteVideo() {
		if (!ui.remoteVideo) return;
		ui.remoteVideo.srcObject = state.call.remoteStream || null;
	}

	function updateCallButtonsState() {
		const stream = state.call.localStream;
		const micEnabled = !!stream?.getAudioTracks()?.some((track) => track.enabled);
		const camEnabled = !!stream?.getVideoTracks()?.some((track) => track.enabled);

		if (ui.toggleMicBtn) {
			ui.toggleMicBtn.setAttribute('aria-pressed', micEnabled ? 'false' : 'true');
			const label = ui.toggleMicBtn.querySelector('span');
			if (label) {
				label.textContent = micEnabled ? 'Mic' : 'Mic tắt';
			}
		}

		if (ui.toggleCamBtn) {
			ui.toggleCamBtn.setAttribute('aria-pressed', camEnabled ? 'false' : 'true');
			const label = ui.toggleCamBtn.querySelector('span');
			if (label) {
				label.textContent = camEnabled ? 'Cam' : 'Cam tắt';
			}
		}
	}

	function toggleLocalTrack(kind) {
		const stream = state.call.localStream;
		if (!stream) return;

		const tracks = kind === 'audio' ? stream.getAudioTracks() : stream.getVideoTracks();
		if (!tracks.length) return;

		tracks.forEach((track) => {
			track.enabled = !track.enabled;
		});

		updateCallButtonsState();
	}

	function setActiveCallState({ role, conversationId, peerId, peerName, localStream }) {
		state.call.active = true;
		state.call.role = role;
		state.call.conversationId = String(conversationId);
		state.call.peerId = Number(peerId || 0);
		state.call.peerName = String(peerName || 'Người dùng');
		state.call.localStream = localStream || null;
		state.call.remoteStream = null;
		state.call.currentCallId = String(conversationId);

		if (ui.callPeerName) {
			ui.callPeerName.textContent = state.call.peerName;
		}

		updateCallButtonsState();
	}

	function pushIceCandidate(conversationId, peerId, candidate) {
		const fieldName = state.call.role === 'caller' ? 'callerCandidates' : 'calleeCandidates';
		const callRef = getConversationDocRef(conversationId);

		getDoc(callRef)
			.then((snapshot) => {
				const existing = snapshot.exists() ? (snapshot.data()?.videoCall || {}) : {};
				const currentList = Array.isArray(existing[fieldName]) ? existing[fieldName] : [];
				const nextList = currentList.concat({
					from: String(state.me.id),
					candidate: candidate.candidate,
					sdpMid: candidate.sdpMid ?? null,
					sdpMLineIndex: Number.isFinite(Number(candidate.sdpMLineIndex)) ? Number(candidate.sdpMLineIndex) : null,
					createdAt: Date.now(),
				});

				return setDoc(callRef, {
					videoCall: {
						...existing,
						[fieldName]: nextList,
						updatedAt: Date.now(),
					},
				}, { merge: true });
			})
			.catch(() => {});
	}

	function createPeerConnection(conversationId, peerId) {
		console.log('[VIDEO_CALL] createPeerConnection for conversationId:', conversationId, 'peerId:', peerId);
		const pc = new RTCPeerConnection({ iceServers: state.rtcIceServers || [] });

		const localStream = state.call.localStream;
		if (localStream) {
			console.log('[VIDEO_CALL] Adding local stream tracks:', localStream.getTracks().length);
			localStream.getTracks().forEach((track) => {
				pc.addTrack(track, localStream);
			});
		}

		pc.ontrack = (event) => {
			console.log('[VIDEO_CALL] ontrack event received');
			const [stream] = event.streams;
			if (!stream) return;
			console.log('[VIDEO_CALL] Remote stream received, tracks:', stream.getTracks().length);
			state.call.remoteStream = stream;
			renderRemoteVideo();
		};

		pc.onicecandidate = (event) => {
			if (!event.candidate) {
				console.log('[VIDEO_CALL] ICE candidate gathering complete');
				return;
			}
			console.log('[VIDEO_CALL] New ICE candidate');
			pushIceCandidate(conversationId, peerId, event.candidate);
		};

		pc.oncandidateerror = (event) => {
			console.warn('[VIDEO_CALL] ICE candidate error:', event.errorText);
		};

		pc.onconnectionstatechange = () => {
			const status = pc.connectionState;
			console.log('[VIDEO_CALL] onconnectionstatechange:', status, '| call.active:', state.call.active, '| lastStatus:', state.call.lastVideoCallState?.status);
			if (status === 'connected') {
				console.log('[VIDEO_CALL] Connection CONNECTED');
				updateCallStatusLabel('Đã kết nối');
				return;
			}

			if (status === 'disconnected') {
				console.log('[VIDEO_CALL] Connection DISCONNECTED');
				updateCallStatusLabel('Mất kết nối...');
				return;
			}

			if (status === 'failed') {
				console.warn('[VIDEO_CALL] Connection FAILED');
				// Only end call if we're already in 'active' status
				// Don't end during 'ringing' phase as it may recover
				if (state.call.lastVideoCallState?.status === 'active') {
					console.warn('[VIDEO_CALL] Failed after being active - ending call');
					endActiveCall('ended').catch(() => {});
				} else {
					console.warn('[VIDEO_CALL] Failed during setup, waiting for recovery');
					updateCallStatusLabel('Kết nối thất bại, đang thử lại...');
				}
				return;
			}

			if (status === 'closed') {
				console.log('[VIDEO_CALL] Connection CLOSED');
				if (state.call.active) {
					console.log('[VIDEO_CALL] Call active, ending call');
					endActiveCall('ended').catch(() => {});
				}
			}
		};

		console.log('[VIDEO_CALL] PeerConnection created');
		return pc;
	}

	function applyRemoteCandidates(videoCall) {
		if (!state.call.pc || !videoCall) return;

		const remoteList = state.call.role === 'caller'
			? (Array.isArray(videoCall.calleeCandidates) ? videoCall.calleeCandidates : [])
			: (Array.isArray(videoCall.callerCandidates) ? videoCall.callerCandidates : []);

		if (!state.call.processedCandidateIds) {
			state.call.processedCandidateIds = new Set();
		}

		while (state.call.remoteCandidatesSeen < remoteList.length) {
			const candidate = remoteList[state.call.remoteCandidatesSeen];
			state.call.remoteCandidatesSeen += 1;

			if (!candidate?.candidate) {
				continue;
			}

			// Use createdAt as unique identifier to prevent duplicate processing
			const candidateId = `${candidate.createdAt}_${candidate.candidate}`;
			if (state.call.processedCandidateIds.has(candidateId)) {
				continue;
			}
			state.call.processedCandidateIds.add(candidateId);

			state.call.pc.addIceCandidate(new RTCIceCandidate({
				candidate: String(candidate.candidate || ''),
				sdpMid: candidate.sdpMid ?? null,
				sdpMLineIndex: Number.isFinite(Number(candidate.sdpMLineIndex)) ? Number(candidate.sdpMLineIndex) : null,
			})).catch(() => {});
		}
	}

	function subscribeCallDoc(conversationId) {
		console.log('[VIDEO_CALL] subscribeCallDoc for:', conversationId);
		if (state.call.callDocUnsub) {
			state.call.callDocUnsub();
			state.call.callDocUnsub = null;
		}

		state.call.remoteCandidatesSeen = 0;
		state.call.processedCandidateIds = new Set();
		let callCreatedAt = null; // Track when this call was initiated
		
		state.call.callDocUnsub = onSnapshot(getConversationDocRef(conversationId), async (snapshot) => {
			if (!snapshot.exists()) {
				console.log('[VIDEO_CALL] Call doc does not exist');
				return;
			}

			const data = snapshot.data() || {};
			const videoCall = getVideoCallData(data);
			if (!videoCall) {
				console.log('[VIDEO_CALL] No videoCall data in snapshot');
				return;
			}

			// On first snapshot, record the call's createdAt timestamp
			if (!callCreatedAt && videoCall.createdAt) {
				callCreatedAt = videoCall.createdAt;
				console.log('[VIDEO_CALL] Recording call createdAt:', callCreatedAt);
			}

			// Verify this is the same call by checking createdAt
			// If createdAt is very different, it's a stale call, ignore it
			if (callCreatedAt && videoCall.createdAt && Math.abs(videoCall.createdAt - callCreatedAt) > 5000) {
				console.warn('[VIDEO_CALL] Ignoring stale call data (createdAt mismatch)');
				return;
			}

			state.call.lastVideoCallState = videoCall;

			const status = String(videoCall.status || '');
			console.log('[VIDEO_CALL] Call status from Firestore:', status, '| call.active:', state.call.active, '| createdAt match:', !callCreatedAt || !videoCall.createdAt || Math.abs(videoCall.createdAt - callCreatedAt) <= 5000);

			// IMPORTANT: For callers, verify the offer matches to avoid processing stale calls
			if (state.call.role === 'caller' && !state.call.pc?.currentRemoteDescription) {
				// First snapshot should contain the offer we just sent
				const hasOffer = !!videoCall.offer?.sdp;
				if (!hasOffer) {
					console.warn('[VIDEO_CALL] No offer in Firestore yet, waiting...');
					return;
				}
			}

			// Setup timeout for ringing phase (for callers)
			if (status === 'ringing' && state.call.role === 'caller') {
				console.log('[VIDEO_CALL] Setting up call timeout for ringing');
				setupCallTimeout();
				updateCallStatusLabel('Đang đổ chuông...');
			}

			// Clear timeout when transitioning away from ringing
			if (status !== 'ringing') {
				console.log('[VIDEO_CALL] Clearing call timeout, status is now:', status);
				clearCallTimeout();
			}

			if (state.call.role === 'caller' && videoCall.answer?.sdp && state.call.pc && !state.call.pc.currentRemoteDescription) {
				console.log('[VIDEO_CALL] Setting remote description from answer');
				try {
					await state.call.pc.setRemoteDescription(new RTCSessionDescription(videoCall.answer));
					updateCallStatusLabel('Đã kết nối');
				} catch (error) {
					console.error('[VIDEO_CALL] Error setting remote description:', error);
				}
			}

			applyRemoteCandidates(videoCall);

			if (status === 'active') {
				console.log('[VIDEO_CALL] Call is now ACTIVE');
				updateCallStatusLabel('Đã kết nối');
			}

			// IMPORTANT: Only process terminal status if the timestamps match to avoid stale data
			const isStaleCall = callCreatedAt && videoCall.createdAt && Math.abs(videoCall.createdAt - callCreatedAt) > 5000;
			if (isCallTerminal(status) && state.call.active && state.call.currentCallId === conversationId && !isStaleCall) {
				console.log('[VIDEO_CALL] Call is TERMINAL with status:', status);
				const map = {
					rejected: 'Đã bị từ chối',
					cancelled: 'Đã hủy cuộc gọi',
					busy: 'Người dùng đang bận',
					ended: 'Cuộc gọi đã kết thúc',
					no_answer: 'Người nhận không trả lời',
				};
				updateCallStatusLabel(map[status] || 'Cuộc gọi đã kết thúc');
				clearCallTimeout();
				window.setTimeout(() => {
					console.log('[VIDEO_CALL] Cleaning up after call end');
					cleanupActiveCall();
				}, 900);
			}
		});
	}

	function syncConversationSnapshot(conversations) {
		if (!Array.isArray(conversations)) return;

		let incoming = null;
		for (const conversation of conversations) {
			const videoCall = getVideoCallData(conversation);
			if (!videoCall) continue;

			if (state.call.active && String(conversation.id) !== String(state.call.currentCallId)) {
				continue;
			}

			if (videoCall.status === 'ringing' && Number(videoCall.calleeId) === Number(state.me.id)) {
				incoming = {
					conversationId: String(conversation.id),
					callerId: Number(videoCall.callerId || 0),
					callerName: String(videoCall.callerName || 'Người dùng'),
					offer: videoCall.offer || null,
					videoCall,
				};
				break;
			}
		}

		if (!incoming) {
			hideIncomingCallCard();
			state.call.incomingData = null;
			state.call.incomingConversationId = null;
			return;
		}

		state.call.incomingData = incoming;
		state.call.incomingConversationId = incoming.conversationId;
		showIncomingCallCard(incoming.callerName);
	}

	async function startVideoCall() {
		console.log('[VIDEO_CALL] startVideoCall triggered');
		const supportIssue = getVideoCallSupportIssue();
		if (supportIssue) {
			console.warn('[VIDEO_CALL] Support issue:', supportIssue);
			toast?.(supportIssue);
			return;
		}

		if (!state.activeConversationId || !state.activePeer?.id) {
			console.warn('[VIDEO_CALL] No active conversation or peer');
			toast?.('Hãy chọn cuộc trò chuyện trước khi gọi.');
			return;
		}

		if (state.call.active) {
			console.log('[VIDEO_CALL] Call already active, showing overlay');
			setCallOverlayVisible(true);
			return;
		}

		const conversationId = state.activeConversationId;
		const peerId = Number(state.activePeer.id);
		const callRef = getConversationDocRef(conversationId);

		const existing = await getDoc(callRef).catch(() => null);
		if (existing?.exists()) {
			const data = existing.data() || {};
			const videoCall = getVideoCallData(data);
			const status = String(videoCall?.status || '');
			if (!isCallTerminal(status) && status !== '') {
				toast?.('Cuộc gọi đang diễn ra. Vui lòng thử lại sau.');
				return;
			}
			// Clear old videoCall data BEFORE starting new call
			console.log('[VIDEO_CALL] Clearing old videoCall data before new call');
			await setDoc(callRef, { videoCall: {} }, { merge: true }).catch(() => {});
		}

		let localStream;
		try {
			localStream = await navigator.mediaDevices.getUserMedia({
				audio: true,
				video: true,
			});
		} catch (error) {
			toast?.(formatGetUserMediaError(error));
			return;
		}

		setActiveCallState({
			role: 'caller',
			conversationId,
			peerId,
			peerName: state.activePeer.username || 'Người dùng',
			localStream,
		});

		console.log('[VIDEO_CALL] Setting overlay visible');
		setCallOverlayVisible(true);
		updateCallStatusLabel('Đang đổ chuông...');
		renderLocalVideo();

		console.log('[VIDEO_CALL] Creating peer connection');
		const pc = createPeerConnection(conversationId, peerId);
		state.call.pc = pc;

		try {
			const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
			await pc.setLocalDescription(offer);

			console.log('[VIDEO_CALL] Sending offer to Firebase');
			await setDoc(callRef, {
				videoCall: {
					conversationId,
					callerId: Number(state.me.id),
					callerName: String(state.me.username || 'Người dùng'),
					calleeId: Number(peerId),
					calleeName: String(state.activePeer.username || 'Người dùng'),
					status: 'ringing',
					offer: {
						type: offer.type,
						sdp: offer.sdp,
					},
					answer: null,
					callerCandidates: [],
					calleeCandidates: [],
					createdAt: Date.now(),
					updatedAt: Date.now(),
					endedAt: null,
					endedBy: null,
				},
			}, { merge: true });

			console.log('[VIDEO_CALL] Subscribing to call doc AFTER setDoc');
			subscribeCallDoc(conversationId);

			console.log('[VIDEO_CALL] Call initiated successfully, currentCallId:', conversationId);
			state.call.currentCallId = conversationId;
		} catch (error) {
			console.error('[VIDEO_CALL] Error starting video call:', error);
			await endActiveCall('ended').catch(() => {});
		}
	}

	async function acceptIncomingCall() {
		const incoming = state.call.incomingData;
		if (!incoming?.conversationId || !incoming?.offer?.sdp) {
			return;
		}

		if (state.call.active) {
			await declineIncomingCall();
			return;
		}

		hideIncomingCallCard();

		const peerId = Number(incoming.callerId || 0);
		if (peerId > 0) {
			await startConversationByUserId(peerId).catch(() => {});
		}

		const supportIssue = getVideoCallSupportIssue();
		if (supportIssue) {
			toast?.(supportIssue);
			await declineIncomingCall().catch(() => {});
			return;
		}

		let localStream;
		try {
			localStream = await navigator.mediaDevices.getUserMedia({
				audio: true,
				video: true,
			});
		} catch (error) {
			toast?.(formatGetUserMediaError(error));
			await declineIncomingCall().catch(() => {});
			return;
		}

		setActiveCallState({
			role: 'callee',
			conversationId: incoming.conversationId,
			peerId,
			peerName: incoming.callerName || 'Người dùng',
			localStream,
		});

		setCallOverlayVisible(true);
		updateCallStatusLabel('Đang kết nối...');
		renderLocalVideo();

		const pc = createPeerConnection(incoming.conversationId, peerId);
		state.call.pc = pc;
		subscribeCallDoc(incoming.conversationId);

		try {
			await pc.setRemoteDescription(new RTCSessionDescription(incoming.offer));
			const answer = await pc.createAnswer();
			await pc.setLocalDescription(answer);

			await setDoc(getConversationDocRef(incoming.conversationId), {
				videoCall: {
					...incoming.videoCall,
					status: 'active',
					answer: {
						type: answer.type,
						sdp: answer.sdp,
					},
					callerCandidates: incoming.videoCall?.callerCandidates || [],
					calleeCandidates: incoming.videoCall?.calleeCandidates || [],
					answeredAt: Date.now(),
					updatedAt: Date.now(),
				},
			}, { merge: true });

			state.call.currentCallId = incoming.conversationId;
			state.call.incomingData = null;
		} catch (error) {
			console.error('Error accepting call:', error);
			await endActiveCall('ended').catch(() => {});
		}
	}

	async function declineIncomingCall() {
		const incoming = state.call.incomingData;
		if (!incoming?.conversationId) {
			hideIncomingCallCard();
			return;
		}

		await setDoc(getConversationDocRef(incoming.conversationId), {
			videoCall: {
				...incoming.videoCall,
				status: 'rejected',
				rejectedBy: String(state.me.id),
				endedBy: String(state.me.id),
				endedAt: Date.now(),
				updatedAt: Date.now(),
			},
		}, { merge: true }).catch(() => {});

		await pushCallOutcomeMessage(incoming.conversationId, 'rejected', incoming.videoCall?.createdAt || null);

		hideIncomingCallCard();
		state.call.incomingData = null;
	}

	async function endActiveCall(status = 'ended') {
		console.log('[VIDEO_CALL] endActiveCall called with status:', status, '| call.active:', state.call.active, '| currentCallId:', state.call.currentCallId);
		if (!state.call.active || !state.call.currentCallId) {
			console.log('[VIDEO_CALL] Call not active or no currentCallId, cleaning up');
			cleanupActiveCall();
			return;
		}

		const callId = String(state.call.currentCallId);
		console.log('[VIDEO_CALL] Updating Firebase with status:', status);
		await setDoc(getConversationDocRef(callId), {
			videoCall: {
				...(state.call.lastVideoCallState || {}),
				status,
				endedBy: String(state.me.id),
				endedAt: Date.now(),
				updatedAt: Date.now(),
			},
		}, { merge: true }).catch((err) => {
			console.error('[VIDEO_CALL] Error updating call status in Firebase:', err);
		});

		await pushCallOutcomeMessage(callId, status, state.call.lastVideoCallState?.createdAt || null);

		console.log('[VIDEO_CALL] Calling cleanupActiveCall');
		cleanupActiveCall();
	}

	function cleanupActiveCall() {
		console.log('[VIDEO_CALL] cleanupActiveCall started');
		hideIncomingCallCard();
		clearCallTimeout();

		if (state.call.callDocUnsub) {
			console.log('[VIDEO_CALL] Unsubscribing from callDoc');
			state.call.callDocUnsub();
			state.call.callDocUnsub = null;
		}

		if (state.call.candidateUnsub) {
			console.log('[VIDEO_CALL] Unsubscribing from candidates');
			state.call.candidateUnsub();
			state.call.candidateUnsub = null;
		}

		if (state.call.pc) {
			console.log('[VIDEO_CALL] Closing peer connection');
			state.call.pc.onicecandidate = null;
			state.call.pc.ontrack = null;
			state.call.pc.onconnectionstatechange = null;
			state.call.pc.oncandidateerror = null;
			state.call.pc.close();
			state.call.pc = null;
		}

		if (state.call.localStream) {
			console.log('[VIDEO_CALL] Stopping local stream tracks');
			state.call.localStream.getTracks().forEach((track) => track.stop());
			state.call.localStream = null;
		}

		state.call.remoteStream = null;
		state.call.active = false;
		state.call.role = null;
		state.call.conversationId = null;
		state.call.peerId = 0;
		state.call.peerName = '';
		state.call.currentCallId = null;
		state.call.lastVideoCallState = null;
		state.call.processedCandidateIds = new Set();
		state.call.incomingData = null;
		state.call.incomingConversationId = null;

		if (ui.localVideo) ui.localVideo.srcObject = null;
		if (ui.remoteVideo) ui.remoteVideo.srcObject = null;
		console.log('[VIDEO_CALL] Hiding call overlay');
		setCallOverlayVisible(false);
		updateCallStatusLabel('');
		console.log('[VIDEO_CALL] cleanupActiveCall completed');
	}

	function setupIncomingCallListener() {
		// Kept for compatibility; incoming calls are synced from conversations snapshots.
	}

	function syncConversationAvailability(conversation) {
		if (!ui.videoCallBtn) return;
		if (!conversation) {
			ui.videoCallBtn.disabled = true;
			return;
		}

		const myId = String(state.me.id);
		const peerId = String(conversation.peer?.id || '');
		const blockedBy = conversation.blockedBy || [];
		const iBlockedThem = blockedBy.includes(myId);
		const theyBlockedMe = blockedBy.includes(peerId);
		ui.videoCallBtn.disabled = iBlockedThem || theyBlockedMe;
	}

	function syncConversationSnapshots(conversations) {
		syncConversationSnapshot(conversations);
	}

	function bindEvents() {
		if (eventsBound) return;
		eventsBound = true;

		if (ui.videoCallBtn) {
			ui.videoCallBtn.addEventListener('click', async () => {
				await startVideoCall();
			});
		}

		if (ui.acceptCallBtn) {
			ui.acceptCallBtn.addEventListener('click', async () => {
				await acceptIncomingCall();
			});
		}

		if (ui.declineCallBtn) {
			ui.declineCallBtn.addEventListener('click', async () => {
				await declineIncomingCall();
			});
		}

		if (ui.endCallBtn) {
			ui.endCallBtn.addEventListener('click', async () => {
				await endActiveCall('ended');
			});
		}

		if (ui.minimizeCallBtn && ui.callOverlay) {
			ui.minimizeCallBtn.addEventListener('click', () => {
				ui.callOverlay.classList.add('d-none');
			});
		}

		if (ui.toggleMicBtn) {
			ui.toggleMicBtn.addEventListener('click', () => {
				toggleLocalTrack('audio');
			});
		}

		if (ui.toggleCamBtn) {
			ui.toggleCamBtn.addEventListener('click', () => {
				toggleLocalTrack('video');
			});
		}
	}

	function endOnUnload() {
		if (state.call?.active) {
			endActiveCall('ended').catch(() => {});
		}
	}

	return {
		bindEvents,
		setupIncomingCallListener,
		syncConversationSnapshot,
		syncConversationSnapshots,
		syncConversationAvailability,
		endOnUnload,
	};
}
