import {
	doc,
	setDoc,
	getDoc,
	onSnapshot,
	serverTimestamp,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js';

export function createVideoCallFeature({ state, ui, startConversationByUserId, toast }) {
	let eventsBound = false;

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
		};
	}

	function getConversationDocRef(conversationId) {
		return doc(state.db, 'conversations', String(conversationId));
	}

	function getVideoCallData(data) {
		return data?.videoCall || null;
	}

	function isCallTerminal(status) {
		return ['ended', 'rejected', 'cancelled', 'busy'].includes(String(status || ''));
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
	}

	function hideIncomingCallCard() {
		if (!ui.incomingCallCard) return;
		ui.incomingCallCard.classList.add('d-none');
	}

	function setCallOverlayVisible(show) {
		if (!ui.callOverlay) return;
		ui.callOverlay.classList.toggle('d-none', !show);
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
		const pc = new RTCPeerConnection({ iceServers: state.rtcIceServers || [] });

		const localStream = state.call.localStream;
		if (localStream) {
			localStream.getTracks().forEach((track) => {
				pc.addTrack(track, localStream);
			});
		}

		pc.ontrack = (event) => {
			const [stream] = event.streams;
			if (!stream) return;
			state.call.remoteStream = stream;
			renderRemoteVideo();
		};

		pc.onicecandidate = (event) => {
			if (!event.candidate) return;
			pushIceCandidate(conversationId, peerId, event.candidate);
		};

		pc.onconnectionstatechange = () => {
			const status = pc.connectionState;
			if (status === 'connected') {
				updateCallStatusLabel('Đã kết nối');
				return;
			}

			if (status === 'disconnected') {
				updateCallStatusLabel('Mất kết nối...');
				return;
			}

			if (status === 'failed' || status === 'closed') {
				endActiveCall('ended').catch(() => {});
			}
		};

		return pc;
	}

	function applyRemoteCandidates(videoCall) {
		if (!state.call.pc || !videoCall) return;

		const remoteList = state.call.role === 'caller'
			? (Array.isArray(videoCall.calleeCandidates) ? videoCall.calleeCandidates : [])
			: (Array.isArray(videoCall.callerCandidates) ? videoCall.callerCandidates : []);

		while (state.call.remoteCandidatesSeen < remoteList.length) {
			const candidate = remoteList[state.call.remoteCandidatesSeen];
			state.call.remoteCandidatesSeen += 1;

			if (!candidate?.candidate) {
				continue;
			}

			state.call.pc.addIceCandidate(new RTCIceCandidate({
				candidate: String(candidate.candidate || ''),
				sdpMid: candidate.sdpMid ?? null,
				sdpMLineIndex: Number.isFinite(Number(candidate.sdpMLineIndex)) ? Number(candidate.sdpMLineIndex) : null,
			})).catch(() => {});
		}
	}

	function subscribeCallDoc(conversationId) {
		if (state.call.callDocUnsub) {
			state.call.callDocUnsub();
			state.call.callDocUnsub = null;
		}

		state.call.remoteCandidatesSeen = 0;
		state.call.callDocUnsub = onSnapshot(getConversationDocRef(conversationId), async (snapshot) => {
			if (!snapshot.exists()) return;

			const data = snapshot.data() || {};
			const videoCall = getVideoCallData(data);
			if (!videoCall) return;
			state.call.lastVideoCallState = videoCall;

			const status = String(videoCall.status || '');

			if (state.call.role === 'caller' && videoCall.answer?.sdp && state.call.pc && !state.call.pc.currentRemoteDescription) {
				await state.call.pc.setRemoteDescription(new RTCSessionDescription(videoCall.answer)).catch(() => {});
				updateCallStatusLabel('Đã kết nối');
			}

			applyRemoteCandidates(videoCall);

			if (status === 'ringing' && state.call.role === 'caller') {
				updateCallStatusLabel('Đang đổ chuông...');
			}

			if (status === 'active') {
				updateCallStatusLabel('Đã kết nối');
			}

			if (isCallTerminal(status) && state.call.active && state.call.currentCallId === conversationId) {
				const map = {
					rejected: 'Đã bị từ chối',
					cancelled: 'Đã hủy cuộc gọi',
					busy: 'Người dùng đang bận',
					ended: 'Cuộc gọi đã kết thúc',
				};
				updateCallStatusLabel(map[status] || 'Cuộc gọi đã kết thúc');
				window.setTimeout(() => {
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
		const supportIssue = getVideoCallSupportIssue();
		if (supportIssue) {
			toast?.(supportIssue);
			return;
		}

		if (!state.activeConversationId || !state.activePeer?.id) {
			toast?.('Hãy chọn cuộc trò chuyện trước khi gọi.');
			return;
		}

		if (state.call.active) {
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

		setCallOverlayVisible(true);
		updateCallStatusLabel('Đang đổ chuông...');
		renderLocalVideo();

		const pc = createPeerConnection(conversationId, peerId);
		state.call.pc = pc;
		subscribeCallDoc(conversationId);

		const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
		await pc.setLocalDescription(offer);

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

		state.call.currentCallId = conversationId;
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
			peerName: incoming.callerName || state.activePeer?.username || 'Người dùng',
			localStream,
		});

		setCallOverlayVisible(true);
		updateCallStatusLabel('Đang kết nối...');
		renderLocalVideo();

		const pc = createPeerConnection(incoming.conversationId, peerId);
		state.call.pc = pc;
		subscribeCallDoc(incoming.conversationId);

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

		hideIncomingCallCard();
		state.call.incomingData = null;
	}

	async function endActiveCall(status = 'ended') {
		if (!state.call.active || !state.call.currentCallId) {
			cleanupActiveCall();
			return;
		}

		const callId = String(state.call.currentCallId);
		await setDoc(getConversationDocRef(callId), {
			videoCall: {
				...(state.call.lastVideoCallState || {}),
				status,
				endedBy: String(state.me.id),
				endedAt: Date.now(),
				updatedAt: Date.now(),
			},
		}, { merge: true }).catch(() => {});

		cleanupActiveCall();
	}

	function cleanupActiveCall() {
		hideIncomingCallCard();

		if (state.call.callDocUnsub) {
			state.call.callDocUnsub();
			state.call.callDocUnsub = null;
		}

		if (state.call.candidateUnsub) {
			state.call.candidateUnsub();
			state.call.candidateUnsub = null;
		}

		if (state.call.pc) {
			state.call.pc.onicecandidate = null;
			state.call.pc.ontrack = null;
			state.call.pc.close();
			state.call.pc = null;
		}

		if (state.call.localStream) {
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
		setCallOverlayVisible(false);
		updateCallStatusLabel('');
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
