// Global incoming call notification system
// Listens to Firebase for incoming calls and shows notification on all pages

import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
import {
	getAuth,
	signInWithCustomToken,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';
import {
	getFirestore,
	collection,
	query,
	where,
	onSnapshot,
	doc,
	setDoc,
	getDoc,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js';

let state = {
	baseUrl: window.__APP_BASE__ || '',
	db: null,
	auth: null,
	me: {
		id: 0,
		firebaseUid: '',
	},
	callListenerUnsub: null,
	outgoingCallListenerUnsub: null,
	incomingCallData: null,
	activeCall: null,
	ringtoneInterval: null,
	ringtoneAudioContext: null,
	// WebRTC state
	localStream: null,
	remoteStream: null,
	pc: null,
	processedCandidateIds: new Set(),
	remoteCandidatesSeen: 0,
	rtcIceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
	// UI state
	isOverlayVisible: false,
	callDocUnsub: null,
};

const ui = {
	get globalCallCard() { return document.getElementById('globalIncomingCallCard'); },
	get globalCallText() { return document.getElementById('globalIncomingCallText'); },
	get globalAcceptBtn() { return document.getElementById('globalAcceptCallBtn'); },
	get globalDeclineBtn() { return document.getElementById('globalDeclineCallBtn'); },
	get globalCallOverlay() { return document.getElementById('globalCallOverlay'); },
	get globalCallPeerName() { return document.getElementById('globalCallPeerName'); },
	get globalCallStatus() { return document.getElementById('globalCallStatus'); },
	get globalAcceptCallOverlayBtn() { return document.getElementById('globalAcceptCallOverlayBtn'); },
	get globalDeclineCallOverlayBtn() { return document.getElementById('globalDeclineCallOverlayBtn'); },
	get globalOpenChatBtn() { return document.getElementById('globalOpenChatBtn'); },
	get globalRemoteVideo() { return document.getElementById('globalRemoteVideo'); },
	get globalLocalVideo() { return document.getElementById('globalLocalVideo'); },
};

function isMessagesPage() {
	const path = String(window.location.pathname || '');
	return path.includes('/messages');
}

function showGlobalCallNotification(callerName) {
	if (!ui.globalCallCard) return;
	if (ui.globalCallText) {
		ui.globalCallText.textContent = `${callerName} đang gọi video cho bạn.`;
	}
	ui.globalCallCard.classList.remove('d-none');
	startGlobalRingtone();
}

function hideGlobalCallNotification() {
	if (!ui.globalCallCard) return;
	ui.globalCallCard.classList.add('d-none');
	stopGlobalRingtone();
}

function showGlobalCallOverlay(callerName) {
	if (!ui.globalCallOverlay) return;
	if (ui.globalCallPeerName) {
		ui.globalCallPeerName.textContent = callerName;
	}
	if (ui.globalCallStatus) {
		ui.globalCallStatus.textContent = 'Đang gọi video...';
	}
	ui.globalCallOverlay.classList.remove('d-none');
	state.isOverlayVisible = true;
	startGlobalRingtone();
}

function hideGlobalCallOverlay() {
	if (!ui.globalCallOverlay) return;
	ui.globalCallOverlay.classList.add('d-none');
	state.isOverlayVisible = false;
	stopGlobalRingtone();
}

function playRingtoneBurst() {
	const AudioContextClass = window.AudioContext || window.webkitAudioContext;
	if (!AudioContextClass) return;

	if (!state.ringtoneAudioContext) {
		state.ringtoneAudioContext = new AudioContextClass();
	}

	const ctx = state.ringtoneAudioContext;
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

function startGlobalRingtone() {
	if (state.ringtoneInterval) return;

	playRingtoneBurst();
	state.ringtoneInterval = window.setInterval(() => {
		playRingtoneBurst();
	}, 1500);
}

function stopGlobalRingtone() {
	if (state.ringtoneInterval) {
		window.clearInterval(state.ringtoneInterval);
		state.ringtoneInterval = null;
	}

	const ctx = state.ringtoneAudioContext;
	if (ctx && ctx.state === 'running') {
		ctx.suspend().catch(() => {});
	}
}

function createPeerConnection() {
	const pc = new RTCPeerConnection({ iceServers: state.rtcIceServers || [] });

	if (state.localStream) {
		state.localStream.getTracks().forEach((track) => {
			pc.addTrack(track, state.localStream);
		});
	}

	pc.ontrack = (event) => {
		const [stream] = event.streams;
		if (!stream) return;
		state.remoteStream = stream;
		if (ui.globalRemoteVideo) {
			ui.globalRemoteVideo.srcObject = stream;
		}
	};

	pc.onicecandidate = (event) => {
		if (!event.candidate) return;
		const conversationId = state.activeCall?.conversationId || state.incomingCallData?.conversationId;
		if (!conversationId) return;
		const role = state.activeCall?.role || 'callee';
		pushIceCandidate(conversationId, event.candidate, role);
	};

	state.pc = pc;
	return pc;
}

function pushIceCandidate(conversationId, candidate, role = 'callee') {
	const fieldName = role === 'caller' ? 'callerCandidates' : 'calleeCandidates';
	const callRef = doc(state.db, 'conversations', String(conversationId));

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

function applyRemoteCandidates(videoCall) {
	if (!state.pc || !videoCall) return;
	const role = state.activeCall?.role || 'callee';
	const remoteList = role === 'caller'
		? (Array.isArray(videoCall.calleeCandidates) ? videoCall.calleeCandidates : [])
		: (Array.isArray(videoCall.callerCandidates) ? videoCall.callerCandidates : []);

	while (state.remoteCandidatesSeen < remoteList.length) {
		const candidate = remoteList[state.remoteCandidatesSeen];
		state.remoteCandidatesSeen += 1;

		if (!candidate?.candidate) continue;

		const candidateId = `${candidate.createdAt}_${candidate.candidate}`;
		if (state.processedCandidateIds.has(candidateId)) continue;
		state.processedCandidateIds.add(candidateId);

		state.pc.addIceCandidate(new RTCIceCandidate({
			candidate: String(candidate.candidate || ''),
			sdpMid: candidate.sdpMid ?? null,
			sdpMLineIndex: Number.isFinite(Number(candidate.sdpMLineIndex)) ? Number(candidate.sdpMLineIndex) : null,
		})).catch(() => {});
	}
}

async function acceptGlobalCall() {
	if (!state.incomingCallData || !state.incomingCallData.offer?.sdp) return;

	try {
		let localStream;
		try {
			localStream = await navigator.mediaDevices.getUserMedia({
				audio: true,
				video: true,
			});
		} catch (error) {
			console.error('[GLOBAL_CALL] getUserMedia error:', error);
			return;
		}

		state.localStream = localStream;
		state.activeCall = {
			conversationId: String(state.incomingCallData.conversationId),
			role: 'callee',
			peerName: state.incomingCallData.callerName,
		};
		if (ui.globalLocalVideo) {
			ui.globalLocalVideo.srcObject = localStream;
		}

		const pc = createPeerConnection();

		// Subscribe to call updates - but don't auto-navigate away
		if (state.callDocUnsub) {
			state.callDocUnsub();
		}

		const callRef = doc(state.db, 'conversations', state.incomingCallData.conversationId);
		state.callDocUnsub = onSnapshot(callRef, async (snapshot) => {
			if (!snapshot.exists()) return;

			const data = snapshot.data() || {};
			const videoCall = data.videoCall || null;
			if (!videoCall) return;

			if (videoCall.status && ['ended', 'cancelled', 'rejected', 'no_answer', 'busy'].includes(String(videoCall.status))) {
				hideGlobalCallOverlay();
				state.activeCall = null;
				state.incomingCallData = null;
				return;
			}

			// Set remote description (offer from caller)
			if (videoCall.offer?.sdp && pc && !pc.currentRemoteDescription) {
				try {
					await pc.setRemoteDescription(new RTCSessionDescription(videoCall.offer));
					const answer = await pc.createAnswer();
					await pc.setLocalDescription(answer);

					await setDoc(callRef, {
						videoCall: {
							...videoCall,
							status: 'active',
							answer: {
								type: answer.type,
								sdp: answer.sdp,
							},
							updatedAt: Date.now(),
						},
					}, { merge: true });

					if (ui.globalCallStatus) {
						ui.globalCallStatus.textContent = 'Đã kết nối';
					}
				} catch (error) {
					console.error('[GLOBAL_CALL] Error setting remote description:', error);
				}
			}

			applyRemoteCandidates(videoCall);
		});
	} catch (error) {
		console.error('[GLOBAL_CALL] Accept call error:', error);
	}
}

async function setupIncomingCallListener() {
	if (!state.db || !state.me.firebaseUid) return;

	if (state.callListenerUnsub) {
		state.callListenerUnsub();
	}

	const q = query(
		collection(state.db, 'conversations'),
		where('videoCall.status', '==', 'ringing'),
		where('videoCall.calleeId', '==', Number(state.me.id)),
	);

	state.callListenerUnsub = onSnapshot(q, (snapshot) => {
		let incoming = null;

		snapshot.forEach((doc) => {
			const data = doc.data() || {};
			const videoCall = data.videoCall || null;

			if (videoCall?.status === 'ringing' && Number(videoCall.calleeId) === Number(state.me.id)) {
				if (Number(videoCall.callerId) === Number(state.me.id)) return;
				incoming = {
					conversationId: String(doc.id),
					callerId: Number(videoCall.callerId || 0),
					callerName: String(videoCall.callerName || 'Người dùng'),
					offer: videoCall.offer || null,
					videoCall,
				};
			}
		});

		if (!incoming) {
			hideGlobalCallNotification();
			state.incomingCallData = null;
			if (!state.activeCall) {
				hideGlobalCallOverlay();
			}
			return;
		}

		state.incomingCallData = incoming;
		
		// Always show notification bar
		showGlobalCallNotification(incoming.callerName);
	}, (error) => {
		console.error('[GLOBAL_CALL] Listener error:', error);
	});
}

function setupGlobalCallEvents() {
	if (ui.globalDeclineBtn) {
		ui.globalDeclineBtn.addEventListener('click', async () => {
			if (!state.incomingCallData) return;

			hideGlobalCallNotification();
			hideGlobalCallOverlay();
			state.incomingCallData = null;
			state.activeCall = null;

			console.log('[GLOBAL_CALL] Call declined');
		});
	}

	if (ui.globalAcceptBtn) {
		ui.globalAcceptBtn.addEventListener('click', async () => {
			if (!state.incomingCallData) return;

			hideGlobalCallNotification();
			showGlobalCallOverlay(state.incomingCallData.callerName);
			
			// Accept and setup WebRTC
			await acceptGlobalCall();
		});
	}

	if (ui.globalAcceptCallOverlayBtn) {
		ui.globalAcceptCallOverlayBtn.addEventListener('click', async () => {
			const conversationId = state.activeCall?.conversationId || state.incomingCallData?.conversationId;
			if (!conversationId) return;
			hideGlobalCallOverlay();
			window.location.href = `${state.baseUrl}/messages?c=${encodeURIComponent(conversationId)}&call=1`;
		});
	}

	if (ui.globalDeclineCallOverlayBtn) {
		ui.globalDeclineCallOverlayBtn.addEventListener('click', async () => {
			if (!state.incomingCallData && !state.activeCall) return;

			hideGlobalCallOverlay();
			state.incomingCallData = null;
			state.activeCall = null;
			
			// Stop all tracks
			if (state.localStream) {
				state.localStream.getTracks().forEach(track => track.stop());
				state.localStream = null;
			}
			
			if (state.pc) {
				state.pc.close();
				state.pc = null;
			}
			
			console.log('[GLOBAL_CALL] Call declined from overlay');
		});
	}

	if (ui.globalOpenChatBtn) {
		ui.globalOpenChatBtn.addEventListener('click', async () => {
			const conversationId = state.activeCall?.conversationId || state.incomingCallData?.conversationId;
			if (!conversationId) return;
			window.location.href = `${state.baseUrl}/messages?c=${encodeURIComponent(conversationId)}&call=1`;
		});
	}
}

async function init() {
	try {
		// Chat page has its own dedicated call flow (video-call.js).
		// Skip global handler here to avoid duplicate listeners/peer-connections.
		if (isMessagesPage()) {
			hideGlobalCallNotification();
			hideGlobalCallOverlay();
			return;
		}

		// Fetch bootstrap data
		const response = await fetch(`${state.baseUrl}/chat-api/bootstrap`, {
			credentials: 'same-origin',
		});

		const data = await response.json();

		if (!data?.firebase || !data?.customToken || !data?.me) {
			console.warn('[GLOBAL_CALL] Missing bootstrap data');
			return;
		}

		state.me = {
			id: Number(data.me.id || 0),
			firebaseUid: String(data.me.firebaseUid || ''),
		};

		// Init Firebase
		const app = initializeApp(data.firebase);
		const auth = getAuth(app);
		state.db = getFirestore(app);
		state.auth = auth;

		// Authenticate
		await signInWithCustomToken(auth, data.customToken);

		// Setup listener
		await setupIncomingCallListener();
		await setupOutgoingCallListener();

		// Setup event listeners
		setupGlobalCallEvents();

		console.log('[GLOBAL_CALL] Initialized successfully');
	} catch (error) {
		console.error('[GLOBAL_CALL] Init error:', error);
	}
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
	if (state.callListenerUnsub) {
		state.callListenerUnsub();
	}
	if (state.outgoingCallListenerUnsub) {
		state.outgoingCallListenerUnsub();
	}
	if (state.callDocUnsub) {
		state.callDocUnsub();
	}
	if (state.localStream) {
		state.localStream.getTracks().forEach(track => track.stop());
	}
	if (state.pc) {
		state.pc.close();
	}
	stopGlobalRingtone();
});

async function setupOutgoingCallListener() {
	if (!state.db || !state.me.firebaseUid) return;

	// Listen for active calls where we are the caller but not currently in chat
	const q = query(
		collection(state.db, 'conversations'),
		where('videoCall.status', '==', 'active'),
		where('videoCall.callerId', '==', Number(state.me.id)),
	);

	if (state.outgoingCallListenerUnsub) {
		state.outgoingCallListenerUnsub();
	}

	state.outgoingCallListenerUnsub = onSnapshot(q, (snapshot) => {
		snapshot.forEach((doc) => {
			const data = doc.data() || {};
			const videoCall = data.videoCall || null;

			// Show overlay if we're calling and callee accepts
			if (videoCall?.status === 'active' && Number(videoCall.callerId) === Number(state.me.id)) {
				// Only show if not already showing and we're on global (not in chat)
				if (!state.isOverlayVisible) {
					const calleeName = data.callee?.name || 'Người dùng';
					state.activeCall = {
						conversationId: String(doc.id),
						role: 'caller',
						peerName: calleeName,
					};
					showGlobalCallOverlay(calleeName);
					hideGlobalCallNotification();
					
					// If we have an existing connection, start listening to updates
					if (!state.pc || state.pc.connectionState === 'new' || state.pc.connectionState === 'connecting') {
						setupOutgoingCallConnection(String(doc.id), videoCall);
					}
				}
			}
		});
	}, (error) => {
		console.error('[GLOBAL_CALL] Outgoing call listener error:', error);
	});
}

async function setupOutgoingCallConnection(conversationId, videoCall) {
	if (!state.pc) {
		// Get local stream if not already have it
		try {
			if (!state.localStream) {
				state.localStream = await navigator.mediaDevices.getUserMedia({
					audio: true,
					video: true,
				});
				if (ui.globalLocalVideo) {
					ui.globalLocalVideo.srcObject = state.localStream;
				}
			}

			state.pc = createPeerConnection();
		} catch (error) {
			console.error('[GLOBAL_CALL] Error getting local stream:', error);
			return;
		}
	}

	// Subscribe to call updates
	if (state.callDocUnsub) {
		state.callDocUnsub();
	}

	const callRef = doc(state.db, 'conversations', conversationId);
	state.callDocUnsub = onSnapshot(callRef, async (snapshot) => {
		if (!snapshot.exists()) return;

		const data = snapshot.data() || {};
		const call = data.videoCall || null;
		if (!call) return;

		if (call.status && ['ended', 'cancelled', 'rejected', 'no_answer', 'busy'].includes(String(call.status))) {
			hideGlobalCallOverlay();
			state.activeCall = null;
			return;
		}

		// Handle answer from callee
		if (call.answer?.sdp && state.pc && !state.pc.currentRemoteDescription) {
			try {
				await state.pc.setRemoteDescription(new RTCSessionDescription(call.answer));
				if (ui.globalCallStatus) {
					ui.globalCallStatus.textContent = 'Đã kết nối';
				}
			} catch (error) {
				console.error('[GLOBAL_CALL] Error setting remote description (caller):', error);
			}
		}

		applyRemoteCandidates(call);
	});
}

// Re-check page type when URL changes (SPA navigation)
window.addEventListener('popstate', () => {
	if (!state.incomingCallData) return;

	const isChatPage = window.location.pathname.includes('/messages');
	if (isChatPage) {
		// On chat page, hide call overlay (chat module will handle it)
		hideGlobalCallOverlay();
		hideGlobalCallNotification();
	} else {
		// On other pages, show notification bar
		hideGlobalCallOverlay();
		showGlobalCallNotification(state.incomingCallData.callerName);
	}
});
