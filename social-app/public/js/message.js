import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
import {
	getAuth,
	signInWithCustomToken,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';
import {
	getFirestore,
	doc,
	setDoc,
	updateDoc,
	getDoc,
	collection,
	addDoc,
	getDocs,
	query,
	where,
	orderBy,
	onSnapshot,
	serverTimestamp,
	limit,
	deleteField,
	arrayUnion,
	arrayRemove,
} from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js';

const root = document.getElementById('chatApp');

if (!root) {
	// no-op for non-chat pages
} else {
	const state = {
		baseUrl:
			root.dataset.baseUrl
			|| (typeof window.__APP_BASE__ === 'string' ? window.__APP_BASE__.replace(/\/$/, '') : '')
			|| '',
		csrfToken: root.dataset.csrfToken || '',
		me: {
			id: Number(root.dataset.currentUserId || 0),
			username: root.dataset.currentUserName || 'Bạn',
			initial: root.dataset.currentUserInitial || 'B',
			firebaseUid: '',
		},
		firebase: null,
		auth: null,
		db: null,
		conversations: [],
		conversationsMap: new Map(),
		activeConversationId: null,
		activePeer: null,
		presenceHeartbeat: null,
		unsubConversations: null,
		unsubMessages: null,
		unsubAttachments: null,
		unsubPeerPresence: null,
		searchUsers: [],
		messageCache: [],
		isLoadingMoreMessages: false,
		hasMoreMessages: true,
		peerMetaCache: new Map(),
		peerMetaPending: new Set(),
		peerMetaNamePending: new Set(),
		rtcIceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
		blockedUserIds: new Set(),
		blockedByUserIds: new Set(),
	};

	const ui = {
		root,
		conversationList: document.getElementById('chatConversationList'),
		searchInput: document.getElementById('chatSearchInput'),
		searchSuggestions: document.getElementById('chatSearchSuggestions'),
		messageList: document.getElementById('chatMessageList'),
		headerName: document.getElementById('chatHeaderName'),
		headerAvatar: document.getElementById('chatHeaderAvatar'),
		headerStatus: document.getElementById('chatHeaderStatus'),
		detailPanel: document.getElementById('chatDetailPanel'),
		closeDetailBtn: document.getElementById('chatCloseDetailBtn'),
		detailName: document.getElementById('chatDetailName'),
		detailHandle: document.getElementById('chatDetailHandle'),
		detailStatus: document.getElementById('chatDetailStatus'),
		detailUser: document.getElementById('chatDetailUser'),
		detailAvatar: document.getElementById('chatDetailAvatar'),
		attachmentList: document.getElementById('chatAttachmentList'),
		attachmentCount: document.getElementById('chatAttachmentCount'),
		textInput: document.getElementById('chatTextInput'),
		composerForm: document.getElementById('chatComposerForm'),
		sendBtn: document.getElementById('chatSendBtn'),
		fileBtn: document.getElementById('chatFileBtn'),
		fileInput: document.getElementById('chatFileInput'),
		emptyState: document.getElementById('chatEmptyState'),
		deleteBtn: document.getElementById('chatDeleteBtn'),
		blockBtn: document.getElementById('chatBlockBtn'),
		confirmDeleteBtn: document.getElementById('chatConfirmDeleteBtn'),
		openDetailBtn: document.getElementById('chatOpenDetailBtn'),
		detailBackBtn: document.getElementById('chatDetailBackBtn'),
		newConversationBtn: document.getElementById('chatNewConversationBtn'),
		videoCallBtn: document.getElementById('chatVideoCallBtn'),
		incomingCallCard: document.getElementById('chatIncomingCallCard'),
		incomingCallText: document.getElementById('chatIncomingCallText'),
		acceptCallBtn: document.getElementById('chatAcceptCallBtn'),
		declineCallBtn: document.getElementById('chatDeclineCallBtn'),
		callOverlay: document.getElementById('chatCallOverlay'),
		remoteVideo: document.getElementById('chatRemoteVideo'),
		localVideo: document.getElementById('chatLocalVideo'),
		callPeerName: document.getElementById('chatCallPeerName'),
		callStatus: document.getElementById('chatCallStatus'),
		toggleMicBtn: document.getElementById('chatToggleMicBtn'),
		toggleCamBtn: document.getElementById('chatToggleCamBtn'),
		endCallBtn: document.getElementById('chatEndCallBtn'),
		minimizeCallBtn: document.getElementById('chatMinimizeCallBtn'),
		mobileBackBtn: document.getElementById('chatMobileBackBtn'),
	};

	const deleteModal = new bootstrap.Modal('#chatDeleteModal');
	const videoCallModulePromise = import(`./video-call.js?v=${encodeURIComponent(root.dataset.jsVersion || '')}`);
	let videoCall = null;

	const videoCallBridge = {
		bindEvents: () => videoCall?.bindEvents(),
		setupIncomingCallListener: () => videoCall?.setupIncomingCallListener(),
		syncConversationSnapshots: (conversations) => videoCall?.syncConversationSnapshots(conversations),
		syncConversationAvailability: (conversation) => videoCall?.syncConversationAvailability(conversation),
		endOnUnload: () => videoCall?.endOnUnload(),
	};

	videoCallModulePromise.catch((error) => {
		console.error(error);
	});

	init().catch((error) => {
		console.error(error);
		toast(buildInitErrorMessage(error));
	});

	function updateMessageNotiBadge() {
		const hasUnread = state.conversations.some(c => {
			if (isHiddenByDelete(c)) return false;
			if (!c.lastSenderId || String(c.lastSenderId) === String(state.me.id)) return false;
			
			const readAt = c.readAt?.[String(state.me.id)];
			const lastMessageTime = c.updatedAt?.toMillis?.() || c.updatedAt;
			const readAtTime = readAt?.toMillis?.() || readAt;
			
			// Unread nếu: chưa đọc HOẶC tin mới hơn lần đọc cuối
			return !c.isRead?.[String(state.me.id)] || (lastMessageTime && readAtTime && lastMessageTime > readAtTime);
		});
		
		// Find message button ở leftbar
		const messageBtnLeft = document.querySelector('.chat-rail-btn[aria-label="Tin nhắn"]');
		if (messageBtnLeft) {
			let badge = messageBtnLeft.querySelector('.message-noti-badge');
			if (hasUnread && !badge) {
				badge = document.createElement('span');
				badge.className = 'message-noti-badge';
				badge.style.cssText = 'position: absolute; top: -8px; right: -8px; width: 12px; height: 12px; background: #ef4444; border-radius: 50%; display: block;';
				messageBtnLeft.style.position = 'relative';
				messageBtnLeft.appendChild(badge);
			} else if (!hasUnread && badge) {
				badge.remove();
			}
		}
	}

	function buildInitErrorMessage(error) {
		const code = String(error?.code || '');
		const message = String(error?.message || '');
		const combined = `${code} ${message}`;

		if (combined.includes('CONFIGURATION_NOT_FOUND')) {
			return 'Firebase Auth chưa cấu hình đúng (CONFIGURATION_NOT_FOUND). Hãy kiểm tra FIREBASE_API_KEY cùng project với service account và mở Firebase Authentication.';
		}

		if (combined.includes('INVALID_CUSTOM_TOKEN') || combined.includes('CREDENTIAL_MISMATCH')) {
			return 'Custom token không hợp lệ hoặc khác project Firebase. Kiểm tra FIREBASE_SERVICE_ACCOUNT_EMAIL/PRIVATE_KEY và FIREBASE_API_KEY.';
		}

		if (combined.includes('API_KEY_INVALID')) {
			return 'FIREBASE_API_KEY không hợp lệ. Vui lòng kiểm tra lại key trong .env.';
		}

		return 'Không thể khởi tạo chat. Vui lòng thử lại sau.';
	}

	async function init() {
		const { createVideoCallFeature } = await videoCallModulePromise;
		videoCall = createVideoCallFeature({
			state,
			ui,
			startConversationByUserId,
			toast,
		});
		videoCallBridge.bindEvents();

		bindStaticEvents();
		setDetailPanelOpen(false);
		toggleEmptyState(true);
		const bootstrapData = await apiGet('/chat-api/bootstrap');
		if (!bootstrapData?.firebase || !bootstrapData?.customToken) {
			throw new Error('Missing Firebase bootstrap response');
		}

		state.me = {
			...state.me,
			...bootstrapData.me,
		};
		state.blockedUserIds = new Set((bootstrapData?.blocks?.blocked || []).map((id) => Number(id || 0)).filter((id) => id > 0));
		state.blockedByUserIds = new Set((bootstrapData?.blocks?.blockedBy || []).map((id) => Number(id || 0)).filter((id) => id > 0));

		state.firebase = initializeApp(bootstrapData.firebase);
		state.auth = getAuth(state.firebase);
		state.db = getFirestore(state.firebase);
		state.rtcIceServers = normalizeIceServers(bootstrapData?.rtc?.iceServers);

		await signInWithCustomToken(state.auth, bootstrapData.customToken);
		setupPresence();
		await subscribeConversations();
		videoCallBridge.setupIncomingCallListener();

		const urlParams = new URLSearchParams(window.location.search);
		const peerId = Number(urlParams.get('user') || 0);
		if (peerId > 0) {
			await startConversationByUserId(peerId);
		}
	}

	function setupPresence() {
		setOwnPresence(true);

		if (state.presenceHeartbeat) {
			window.clearInterval(state.presenceHeartbeat);
		}

		state.presenceHeartbeat = window.setInterval(() => {
			setOwnPresence(true);
		}, 45_000);

		document.addEventListener('visibilitychange', () => {
			setOwnPresence(!document.hidden);
		});

		window.addEventListener('beforeunload', () => {
			setOwnPresence(false);
			videoCallBridge.endOnUnload();
		});
	}

	async function setOwnPresence(isOnline) {
		if (!state.db || !state.me.firebaseUid) {
			return;
		}

		try {
			const presenceKey = `app_${state.me.id}`;
			await setDoc(doc(state.db, 'user_presence', presenceKey), {
				uid: state.me.firebaseUid,
				appUserId: String(state.me.id),
				username: state.me.username,
				isOnline: !!isOnline,
				lastActiveAt: serverTimestamp(),
			}, { merge: true });
		} catch (error) {
			// Silently fail
		}
	}

	function subscribePeerPresence(peerId) {
		if (state.unsubPeerPresence) {
			state.unsubPeerPresence();
			state.unsubPeerPresence = null;
		}

		if (!peerId) {
			ui.headerStatus.textContent = 'Ngoại tuyến';
			ui.detailStatus.textContent = 'Ngoại tuyến';
			return;
		}

		const uid = `app_${peerId}`;
		state.unsubPeerPresence = onSnapshot(
			doc(state.db, 'user_presence', uid),
			(snapshot) => {
				const data = snapshot.exists() ? snapshot.data() : null;
				const statusText = formatPresenceStatus(data);
				ui.headerStatus.textContent = statusText;
				ui.detailStatus.textContent = statusText;
			},
			(error) => {
				ui.headerStatus.textContent = 'Ngoại tuyến';
				ui.detailStatus.textContent = 'Ngoại tuyến';
			},
		);
	}

	function formatPresenceStatus(data) {
		if (!data) {
			return 'Ngoại tuyến';
		}

		if (data.isOnline === true) {
			return 'Đang hoạt động';
		}

		const ms = toMillis(data.lastActiveAt);
		if (ms <= 0) {
			return 'Ngoại tuyến';
		}

		const diffMin = Math.floor((Date.now() - ms) / 60_000);
		
		// <= 3 phút: Đang hoạt động
		if (diffMin <= 3) {
			return 'Đang hoạt động';
		}

		// < 60 phút: X phút trước
		if (diffMin < 60) {
			return `${diffMin} phút trước`;
		}

		const diffHours = Math.floor(diffMin / 60);
		const diffDays = Math.floor(diffHours / 24);

		// < 24 giờ: X giờ Y phút trước
		if (diffHours < 24) {
			const mins = diffMin % 60;
			if (mins === 0) {
				return `${diffHours} giờ trước`;
			}
			return `${diffHours} giờ ${mins} phút trước`;
		}

		// <= 7 ngày: X ngày trước
		if (diffDays <= 7) {
			return `${diffDays} ngày trước`;
		}

		// > 7 ngày: Không hoạt động
		return 'Không hoạt động';
	}

	function bindStaticEvents() {
		ui.composerForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			await sendTextMessage();
		});

		// Mark as read when focus on message input
		ui.textInput.addEventListener('focus', () => {
			if (state.activeConversationId) {
				markConversationAsRead(state.activeConversationId);
			}
		});

		ui.fileBtn.addEventListener('click', () => ui.fileInput.click());
		ui.fileInput.addEventListener('change', async () => {
			await sendAttachmentMessages();
			ui.fileInput.value = '';
		});

		ui.textInput.addEventListener('input', debounce(() => {
			if (ui.textInput.value.trim()) {
				setOwnPresence(true);
			}
		}, 500));

		ui.searchInput.addEventListener('input', debounce(async () => {
			const keyword = ui.searchInput.value.trim().toLowerCase();
			renderConversationList(keyword);

			if (keyword.length < 2) {
				state.searchUsers = [];
				renderUserSuggestions();
				return;
			}

			const data = await apiGet(`/chat-api/users?q=${encodeURIComponent(keyword)}&limit=12`);
			state.searchUsers = data?.items ?? [];
			renderUserSuggestions();
		}, 250));

		// Image viewer
		const imageViewer = document.getElementById('chatImageViewer');
		const closeBtn = document.getElementById('chatImageViewerClose');
		if (imageViewer && closeBtn) {
			closeBtn.addEventListener('click', () => {
				imageViewer.style.display = 'none';
			});
			imageViewer.addEventListener('click', (e) => {
				if (e.target === imageViewer) {
					imageViewer.style.display = 'none';
				}
			});
			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape' && imageViewer.style.display === 'flex') {
					imageViewer.style.display = 'none';
				}
			});
		}

		ui.deleteBtn.addEventListener('click', () => {
			if (!state.activeConversationId) {
				return;
			}
			deleteModal.show();
		});

		ui.confirmDeleteBtn.addEventListener('click', async () => {
			await deleteConversationFromInbox();
			deleteModal.hide();
		});

		ui.blockBtn.addEventListener('click', async () => {
			await toggleBlockUser();
		const conversation = state.conversationsMap.get(state.activeConversationId);
		if (conversation) {
			syncHeaderAndDetails(conversation);
			updateComposerBlockedState(conversation);
		}
	});

	ui.openDetailBtn.addEventListener('click', () => {
		setDetailPanelOpen(!ui.root.classList.contains('chat-detail-open'));
	});

	if (ui.detailBackBtn) {
		ui.detailBackBtn.addEventListener('click', () => {
			setDetailPanelOpen(false);
		});
	}

	if (ui.closeDetailBtn) {
		ui.closeDetailBtn.addEventListener('click', () => {
			setDetailPanelOpen(false);
		});
	}

	videoCallBridge.bindEvents();

	if (ui.mobileBackBtn) {
		ui.mobileBackBtn.addEventListener('click', () => {
			ui.root.classList.remove('chat-mobile-thread-open');
			setDetailPanelOpen(false);
		});
	}

	window.addEventListener('resize', debounce(() => {
		if (window.innerWidth > 767.98) {
			ui.root.classList.remove('chat-mobile-thread-open');
		}
	}, 120));

	const openActivePeerProfile = () => {
		const username = String(state.activePeer?.username || '').trim();
		if (!username) return;
		window.location.assign(`${state.baseUrl}/profile?u=${encodeURIComponent(username)}`);
	};

	if (ui.detailAvatar) {
		ui.detailAvatar.addEventListener('click', openActivePeerProfile);
		ui.detailAvatar.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				openActivePeerProfile();
			}
		});
	}

	// User profile menu
	const avatarBtn = document.getElementById('chatAvatarBtn');
	const userMenu = document.getElementById('chatUserMenu');
	if (avatarBtn && userMenu) {
		avatarBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			userMenu.classList.toggle('active');
		});

		// Close menu when clicking outside
		document.addEventListener('click', () => {
			userMenu.classList.remove('active');
		});

		// Prevent menu from closing when clicking inside it
		userMenu.addEventListener('click', (e) => {
			e.stopPropagation();
		});
	}

	// Logout handler
	const logoutBtn = document.getElementById('logoutBtn');
	if (logoutBtn) {
		logoutBtn.addEventListener('click', (e) => {
			e.preventDefault();
			const csrf = logoutBtn.getAttribute('data-csrf');
			const logoutUrl = logoutBtn.getAttribute('data-logout-url');

			// Create form dynamically
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = logoutUrl;
			form.innerHTML = `<input type="hidden" name="_csrf" value="${csrf}">`;
			document.body.appendChild(form);
			setTimeout(() => form.submit(), 100);
		});
	}

	// Empty state button
	const emptyStateBtn = document.getElementById('chatEmptyStateBtn');
	if (emptyStateBtn) {
		emptyStateBtn.addEventListener('click', () => {
			const modal = new bootstrap.Modal('#chatNewConversationModal');
			modal.show();
		});
	}

	// New conversation modal - Load following users
	const newConvModal = document.getElementById('chatNewConversationModal');
	const newConvSearch = document.getElementById('chatNewConvSearch');
	const newConvSuggestions = document.getElementById('chatNewConvSuggestions');

	if (newConvModal) {
		newConvModal.addEventListener('show.bs.modal', async () => {
			// Load all following users when modal opens
			try {
				const response = await fetch(`${state.baseUrl}/user-api/follow?action=following&limit=20`);
				const data = await response.json();
				const users = data?.following ?? [];
				renderNewConvSuggestions(users);
			} catch (error) {
				console.error('Error loading following list:', error);
				newConvSuggestions.innerHTML = '';
			}
		});
	}

	if (newConvSearch) {
		newConvSearch.addEventListener('input', debounce(async () => {
			const keyword = newConvSearch.value.trim().toLowerCase();

			if (keyword.length < 1) {
				// Load all following users when search is empty
				try {
				const response = await fetch(`${state.baseUrl}/user-api/follow?action=following&limit=20`);
				} catch (error) {
					console.error('Error loading following list:', error);
					newConvSuggestions.innerHTML = '';
				}
				return;
			}

			// Search in following users
			try {
				const response = await fetch(`${state.baseUrl}/user-api/follow?action=following&limit=100`);
				const data = await response.json();
				const allFollowing = data?.following ?? [];

				// Filter by keyword
				const filteredUsers = allFollowing.filter(user =>
					user.username?.toLowerCase().includes(keyword) ||
					user.email?.toLowerCase().includes(keyword)
				);

				renderNewConvSuggestions(filteredUsers.slice(0, 20));
			} catch (error) {
				console.error('Error searching following:', error);
				newConvSuggestions.innerHTML = '';
			}
		}, 300));
	}

	function renderNewConvSuggestions(users) {
		newConvSuggestions.innerHTML = users.map((user) => {
			const avatarColor = avatarColorByName(user.username);
			const avatarUrl = resolveAvatarUrl(user.avatarSrc || user.avatarUrl || '');
			const initials = (user.username?.charAt(0).toUpperCase() || 'U');
			return `
				<div class="d-flex align-items-center gap-2 p-2 border-bottom chat-user-suggestion" data-user-id="${user.id}" style="cursor: pointer;">
					${avatarUrl
						? `<div style="position:relative; width:40px; height:40px; flex-shrink:0;">
								<img src="${escapeHtml(avatarUrl)}" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
								<div style="display:none; width:100%; height:100%; border-radius:50%; background:${avatarColor.bg}; color:${avatarColor.fg}; align-items:center; justify-content:center; font-weight:600;">${escapeHtml(initials)}</div>
							</div>`
						: `<div style="width: 40px; height: 40px; border-radius: 50%; background: ${avatarColor.bg}; color: ${avatarColor.fg}; display: flex; align-items: center; justify-content: center; font-weight: 600;">
								${escapeHtml(initials)}
							</div>`}
					<div>
						<div style="font-weight: 500;">${escapeHtml(user.username)}</div>
						<small style="color: #6b7280;">${escapeHtml(user.email)}</small>
					</div>
				</div>
			`;
		}).join('');

		newConvSuggestions.querySelectorAll('.chat-user-suggestion').forEach((el) => {
			el.addEventListener('click', async () => {
				const userId = Number(el.dataset.userId);
				const modal = bootstrap.Modal.getInstance('#chatNewConversationModal');
				if (modal) modal.hide();
				await startConversationByUserId(userId);
				newConvSearch.value = '';
				newConvSuggestions.innerHTML = '';
			});
		});
	}
	
	const suggestionsDiv = document.getElementById('chatNewConvSuggestions');
	if (ui.searchInput) {
		ui.searchInput.addEventListener('input', debounce(async () => {
			const keyword = ui.searchInput.value.trim().toLowerCase();
				if (keyword.length < 1) {
					suggestionsDiv.innerHTML = '';
					return;
				}

				const data = await apiGet(`/chat-api/users?q=${encodeURIComponent(keyword)}&limit=20`);
				const users = data?.items ?? [];
				
				suggestionsDiv.innerHTML = users.map((user) => {
					const avatarColor = avatarColorByName(user.username);
					const avatarUrl = resolveAvatarUrl(user.avatarSrc || user.avatarUrl || '');
					return `
						<div class="d-flex align-items-center gap-2 p-2 border-bottom chat-user-suggestion" data-user-id="${user.id}">
							${avatarUrl
								? `<div style="position:relative; width:40px; height:40px; flex-shrink:0;">
										<img src="${escapeHtml(avatarUrl)}" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
										<div style="display:none; width:100%; height:100%; border-radius:50%; background:${avatarColor.bg}; color:${avatarColor.fg}; align-items:center; justify-content:center; font-weight:600;">${escapeHtml(user.initials)}</div>
									</div>`
								: `<div style="width: 40px; height: 40px; border-radius: 50%; background: ${avatarColor.bg}; color: ${avatarColor.fg}; display: flex; align-items: center; justify-content: center; font-weight: 600;">${escapeHtml(user.initials)}</div>`}
							<div>
								<div style="font-weight: 500;">${escapeHtml(user.username)}</div>
								<small style="color: #6b7280;">${escapeHtml(user.email)}</small>
							</div>
						</div>
					`;
				}).join('');

				suggestionsDiv.querySelectorAll('.chat-user-suggestion').forEach((el) => {
					el.addEventListener('click', async () => {
						const userId = Number(el.dataset.userId);
						const modal = bootstrap.Modal.getInstance('#chatNewConversationModal');
						if (modal) modal.hide();
						await startConversationByUserId(userId);
						ui.searchInput.value = '';
						suggestionsDiv.innerHTML = '';
					});
				});
			}, 300));
		}
	}
	async function subscribeConversations() {
		if (state.unsubConversations) {
			state.unsubConversations();
		}

		const q = query(
			collection(state.db, 'conversations'),
			where('participantKeys', 'array-contains', state.me.firebaseUid),
			orderBy('updatedAt', 'desc'),
			limit(50),
		);

		state.unsubConversations = onSnapshot(q, (snapshot) => {
			const rows = [];
			snapshot.forEach((item) => {
				const data = item.data();
				const normalized = normalizeConversation(item.id, data);

				rows.push(normalized);
			});

			// Track visibility changes
			const oldVisibility = new Map(state.conversations.map(c => [c.id, !isHiddenByDelete(c)]));
			const oldOrder = state.conversations.map(c => c.id);
			
			// Only re-render if conversation was added/removed, not just reordered
			const oldIds = new Set(state.conversations.map(c => c.id));
			const newIds = new Set(rows.map(c => c.id));
			const hasAddedOrRemoved = oldIds.size !== newIds.size || 
				![...newIds].every(id => oldIds.has(id));

			state.conversations = rows;
			state.conversationsMap = new Map(rows.map((entry) => [entry.id, entry]));
			videoCallBridge.syncConversationSnapshots(rows);

			rows.forEach((entry) => {
				const peerId = Number(entry?.peer?.id || 0);
				if (peerId > 0 && !entry?.peer?.avatarSrc && !entry?.peer?.avatarUrl) {
					ensurePeerMeta(peerId);
				} else if (peerId <= 0 && !entry?.peer?.avatarSrc && !entry?.peer?.avatarUrl) {
					ensurePeerMetaByUsername(entry?.peer?.username || '');
				}
			});
			
			// Check if order changed (for unselected conversations)
			const newOrder = rows.map(c => c.id);
			const hasOrderChange = !oldOrder.every((id, i) => id === newOrder[i]);
			
			// Check if any conversation visibility changed (hidden <-> visible)
			// But exclude active conversation - don't render if it's just becoming visible
			const hasVisibilityChange = rows.some(c => {
				if (c.id === state.activeConversationId) {
					return false; // Don't trigger render for active conversation visibility change
				}
				const wasVisible = oldVisibility.get(c.id);
				const isNowVisible = !isHiddenByDelete(c);
				return wasVisible !== undefined && wasVisible !== isNowVisible;
			});
			
			// Always render if no active conversation and order changed (to show new messages at top)
			if (hasAddedOrRemoved || hasVisibilityChange || hasOrderChange) {
				renderConversationList(ui.searchInput.value.trim().toLowerCase());
			} else {
				// Update all conversation items in list without full re-render
				state.conversations.forEach(c => {
					// Always update active conversation, even if hidden
					// (it might temporarily appear hidden due to race condition in timestamp comparison)
					const shouldUpdate = c.id === state.activeConversationId || !isHiddenByDelete(c);
					if (shouldUpdate) {
						updateConversationItemInList(c.id);
					}
				});
			}
			
			// Update notification badge in left menu
			updateMessageNotiBadge();

			if (state.activeConversationId && state.conversationsMap.has(state.activeConversationId)) {
				const updated = state.conversationsMap.get(state.activeConversationId);
				syncHeaderAndDetails(updated);
				
				// Re-subscribe messages if not already subscribed (e.g., after deletion)
				if (!state.unsubMessages) {
					subscribeMessages(state.activeConversationId);
				}
				
				return;
			}
		});
	}

	function updateConversationItemInList(conversationId) {
		const conv = state.conversationsMap.get(conversationId);
		if (!conv) return;

		const button = ui.conversationList.querySelector(`[data-conversation-id="${conversationId}"]`);
		if (!button) return;

		// Unread logic: từ người khác AND (chưa đọc HOẶC tin mới hơn lần đọc cuối)
		let isUnread = false;
		if (conv.lastSenderId && String(conv.lastSenderId) !== String(state.me.id)) {
			const readAt = conv.readAt?.[String(state.me.id)];
			const lastMessageTime = toMillis(conv.updatedAt);
			const readAtTime = toMillis(readAt);
			
			// Unread nếu: chưa từng đọc, hoặc tin mới hơn lần đọc cuối
			isUnread = !conv.isRead?.[String(state.me.id)] || (lastMessageTime > 0 && readAtTime > 0 && lastMessageTime > readAtTime);
		}
		
		// Update preview text
		const preview = button.querySelector('.chat-conversation-preview');
		if (preview) {
			preview.textContent = escapeHtml(conv.lastMessageText || 'Bắt đầu cuộc trò chuyện');
			preview.style.fontWeight = isUnread ? '600' : '';
		}

		// Update time
		const timeSpan = button.querySelector('.chat-conversation-top span');
		if (timeSpan) {
			timeSpan.textContent = escapeHtml(formatShortTime(conv.updatedAt));
		}

		// Update username style
		const username = button.querySelector('strong');
		if (username) {
			username.style.fontWeight = isUnread ? '700' : '';
		}

		// Update/remove badge
		const badgeEl = button.querySelector('.unread-badge');
		if (isUnread && !badgeEl) {
			// Add badge if unread and doesn't exist
			const badgeDiv = document.createElement('div');
			badgeDiv.className = 'unread-badge';
			badgeDiv.style.cssText = 'width: 12px; height: 12px; border-radius: 50%; background: #ef4444; flex-shrink: 0;';
			button.appendChild(badgeDiv);
		} else if (!isUnread && badgeEl) {
			// Remove badge if read
			badgeEl.remove();
		}
	}


	function renderConversationList(keyword = '') {
		let rows = keyword === ''
			? state.conversations
			: state.conversations.filter((item) => {
				const haystack = `${item.peer.username} ${item.peer.email}`.toLowerCase();
				return haystack.includes(keyword);
			});

		//rows = rows.filter((item) => !isUserBlockedRelation(Number(item?.peer?.id || 0)));

		// Filter hidden conversations by deletion, but ALWAYS show active conversation
		rows = rows.filter((item) => {
			if (item.id === state.activeConversationId) {
				return true; // Always show active conversation
			}
			return !isHiddenByDelete(item);
		});

		if (rows.length === 0) {
			ui.conversationList.innerHTML = '<div class="chat-muted">Chưa có cuộc trò chuyện.</div>';
			return;
		}

		ui.conversationList.innerHTML = rows.map((item) => {
			const isActive = item.id === state.activeConversationId;
			const avatarStyle = avatarStyleByName(item.peer.username);
			const avatarUrl = resolveAvatarUrl(item.peer.avatarSrc || item.peer.avatarUrl || '');
			const isFromOther = item.lastSenderId && String(item.lastSenderId) !== String(state.me.id);
			
			// Unread nếu: từ người khác AND (chưa đọc HOẶC tin mới hơn lần đọc cuối)
			let isUnread = false;
			if (isFromOther) {
				const readAt = item.readAt?.[String(state.me.id)];
				const lastMessageTime = toMillis(item.updatedAt);
				const readAtTime = toMillis(readAt);
				
				// Unread nếu: chưa từng đọc, hoặc tin mới hơn lần đọc cuối
				isUnread = !item.isRead?.[String(state.me.id)] || (lastMessageTime > 0 && readAtTime > 0 && lastMessageTime > readAtTime);
			}
			
			const lastMessageStyle = isUnread ? 'font-weight: 600;' : '';
			
			// Avatar render: nếu có URL thì hiển thị ảnh + fallback text, không thì chỉ text
			let avatarHtml = '';
			if (avatarUrl) {
				avatarHtml = `
					<div style="position:relative; width:40px; height:40px;">
						<img id="conv-avatar-${item.id}" 
							 src="${escapeHtml(avatarUrl)}" 
							 class="rounded-circle" 
							 style="width:100%; height:100%; object-fit:cover;"
							 onerror="document.getElementById('conv-avatar-${item.id}').style.display='none'; document.getElementById('conv-avatar-text-${item.id}').style.display='flex';">
						<div id="conv-avatar-text-${item.id}" 
							 class="d-none" 
							 style="position:absolute; top:0; left:0; width:100%; height:100%; ${avatarStyle}; display:none; align-items:center; justify-content:center; border-radius:50%;">
							 ${escapeHtml(item.peer.initials)}
						</div>
					</div>
				`;
			} else {
				avatarHtml = `<div class="chat-conversation-avatar" style="${avatarStyle}">${escapeHtml(item.peer.initials)}</div>`;
			}
			
			return `
				<button type="button" class="chat-conversation-item ${isActive ? 'active' : ''}" data-conversation-id="${item.id}">
					${avatarHtml}
					<div class="chat-conversation-body">
						<div class="chat-conversation-top">
							<strong style="${isUnread ? 'font-weight: 700;' : ''}">${escapeHtml(item.peer.username)}</strong>
							<span>${escapeHtml(formatShortTime(item.updatedAt))}</span>
						</div>
						<div class="chat-conversation-preview" style="${lastMessageStyle}">${escapeHtml(item.lastMessageText || 'Bắt đầu cuộc trò chuyện')}</div>
					</div>
					${isUnread ? '<div class="unread-badge" style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444; flex-shrink: 0;"></div>' : ''}
				</button>
			`;
		}).join('');

		ui.conversationList.querySelectorAll('[data-conversation-id]').forEach((button) => {
			button.addEventListener('click', () => {
				selectConversation(button.dataset.conversationId);
			});
		});
	}

	function renderUserSuggestions() {
		const visibleUsers = state.searchUsers.filter((item) => !isUserBlockedRelation(Number(item?.id || 0)));
		if (visibleUsers.length === 0) {
			ui.searchSuggestions.classList.add('d-none');
			ui.searchSuggestions.innerHTML = '';
			return;
		}

		ui.searchSuggestions.classList.remove('d-none');
		ui.searchSuggestions.innerHTML = visibleUsers.map((item) => `
			<button type="button" class="chat-suggestion-item" data-user-id="${item.id}">
					${resolveAvatarUrl(item.avatarSrc || item.avatarUrl || '')
						? `<span style="position:relative; width:30px; height:30px; display:inline-flex; flex-shrink:0;">
								<img src="${escapeHtml(resolveAvatarUrl(item.avatarSrc || item.avatarUrl || ''))}" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
								<span class="chat-conversation-avatar" style="display:none; ${avatarStyleByName(item.username)}">${escapeHtml(item.initials)}</span>
							</span>`
						: `<span class="chat-conversation-avatar" style="${avatarStyleByName(item.username)}">${escapeHtml(item.initials)}</span>`}
				<span>
					<strong>${escapeHtml(item.username)}</strong>
					<small>${escapeHtml(item.email)}</small>
				</span>
			</button>
		`).join('');

		ui.searchSuggestions.querySelectorAll('[data-user-id]').forEach((button) => {
			button.addEventListener('click', async () => {
				const userId = Number(button.dataset.userId || 0);
				await startConversationByUserId(userId);
				ui.searchSuggestions.classList.add('d-none');
				ui.searchSuggestions.innerHTML = '';
				ui.searchInput.value = '';
			});
		});
	}

	async function startConversationByUserId(userId) {
		if (!userId || userId === state.me.id) {
			return;
		}

		if (isUserBlockedRelation(Number(userId))) {
			toast('Không thể nhắn tin với người dùng này.');
			return;
		}

		const data = await apiGet(`/chat-api/users/${userId}`);
		const user = data?.item;
		if (!user) {
			toast('Không thể nhắn tin với người dùng này.');
			return;
		}

		state.peerMetaCache.set(Number(user.id || 0), user);

		const conversationId = buildConversationId(state.me.id, user.id);
		const convRef = doc(state.db, 'conversations', conversationId);

		const participants = [String(state.me.id), String(user.id)];
		const participantKeys = [`app_${state.me.id}`, `app_${user.id}`];

		const commonPayload = {
			participants,
			participantKeys,
			participantMeta: {
				[String(state.me.id)]: {
					id: state.me.id,
					username: state.me.username,
					email: '',
					avatarUrl: state.me.avatarUrl || state.me.avatarSrc || '',
					avatarSrc: state.me.avatarSrc || '',
					initials: state.me.initial,
				},
				[String(user.id)]: {
					id: user.id,
					username: user.username,
					email: user.email,
					avatarUrl: user.avatarUrl || '',
					avatarSrc: user.avatarSrc || '',
					initials: user.initials,
				},
			},
			updatedAt: serverTimestamp(),
		};

		try {
			await updateDoc(convRef, commonPayload);
		} catch (error) {
			await setDoc(convRef, {
				...commonPayload,
				createdAt: serverTimestamp(),
				blockedBy: [],
				lastMessageText: '',
				lastMessageType: 'text',
			});
		}
		selectConversation(conversationId);
	}

	function selectConversation(conversationId) {
		state.activeConversationId = conversationId;
		if (window.matchMedia('(max-width: 767.98px)').matches) {
			ui.root.classList.add('chat-mobile-thread-open');
		}
		const conversation = state.conversationsMap.get(conversationId);
		syncHeaderAndDetails(conversation);
		subscribeMessages(conversationId);
		subscribeAttachments(conversationId);
		toggleEmptyState(false);
		renderConversationList(ui.searchInput.value.trim().toLowerCase());
		
		// Mark conversation as read
		markConversationAsRead(conversationId);
	}

	async function markConversationAsRead(conversationId) {
		try {
			await updateDoc(doc(state.db, 'conversations', conversationId), {
				[`isRead.${String(state.me.id)}`]: true,
				[`readAt.${String(state.me.id)}`]: serverTimestamp(),
			});
		} catch (error) {
			console.error('Mark as read failed', error);
		}
	}

	function syncHeaderAndDetails(conversation) {
		if (!conversation) {
			ui.headerName.textContent = 'Chọn cuộc trò chuyện';
			ui.headerAvatar.textContent = '?';
			ui.headerStatus.textContent = 'Ngoại tuyến';
			ui.detailName.textContent = 'Chưa chọn';
			ui.detailHandle.textContent = '';
			ui.detailStatus.textContent = 'Ngoại tuyến';
			ui.attachmentCount.textContent = '0';
			ui.attachmentList.innerHTML = '';
			ui.headerAvatar.innerHTML = '?';
			setAvatarFallback(ui.headerAvatar, 'User', '?');
			if (ui.detailAvatar) {
				ui.detailAvatar.innerHTML = '?';
				setAvatarFallback(ui.detailAvatar, 'User', '?');
				ui.detailAvatar.style.cursor = 'default';
				ui.detailAvatar.removeAttribute('role');
				ui.detailAvatar.removeAttribute('tabindex');
				ui.detailAvatar.removeAttribute('title');
			}
			state.activePeer = null;
			ui.root.classList.remove('chat-mobile-thread-open');
			setDetailPanelOpen(false);
			videoCallBridge.syncConversationAvailability(null);
			subscribePeerPresence(0);
			updateComposerBlockedState(null);
			return;
		}

		state.activePeer = conversation.peer;

		ui.headerName.textContent = conversation.peer.username;
		setAvatarElement(ui.headerAvatar, {
			name: conversation.peer.username,
			initials: conversation.peer.initials,
			url: resolveAvatarUrl(conversation.peer.avatarSrc || conversation.peer.avatarUrl || ''),
		});

		ui.detailName.textContent = conversation.peer.username;
		ui.detailHandle.textContent = conversation.peer.email ? `@${conversation.peer.email}` : '';
		if (ui.detailAvatar) {
			setAvatarElement(ui.detailAvatar, {
				name: conversation.peer.username,
				initials: conversation.peer.initials,
				url: resolveAvatarUrl(conversation.peer.avatarSrc || conversation.peer.avatarUrl || ''),
			});
			ui.detailAvatar.style.cursor = 'pointer';
			ui.detailAvatar.setAttribute('role', 'link');
			ui.detailAvatar.setAttribute('tabindex', '0');
			ui.detailAvatar.setAttribute('title', 'Xem trang cá nhân');
		}
		subscribePeerPresence(conversation.peer.id);

		const myId = String(state.me.id);
		const peerId = String(conversation.peer?.id || '');
		const blockedBy = conversation.blockedBy || [];
		const iBlockedThem = blockedBy.includes(myId);
		const theyBlockedMe = blockedBy.includes(peerId);
		
		// Only show block button if they haven't blocked me
		if (theyBlockedMe) {
			ui.blockBtn.style.display = 'none';
		} else {
			ui.blockBtn.style.display = 'block';
			ui.blockBtn.textContent = iBlockedThem ? 'Bỏ chặn người dùng' : 'Chặn người dùng';
		}

		videoCallBridge.syncConversationAvailability(conversation);

		updateComposerBlockedState(conversation);
	}

	function updateComposerBlockedState(conversation) {
		const myId = String(state.me.id);
		const peerId = conversation ? String(conversation.peer?.id || '') : '';
		const blockedBy = conversation?.blockedBy || [];
		const isBlockedInDb = isUserBlockedRelation(Number(peerId || 0));

		// Check if I blocked them or if they blocked me
		const iBlockedThem = blockedBy.includes(myId);
		const theyBlockedMe = blockedBy.includes(peerId);
		const isBlocked = iBlockedThem || theyBlockedMe || isBlockedInDb;
		
		// Get parent of composer form to insert blocked message outside the form
		const composerParent = ui.composerForm.parentElement;
		let blockedMsg = composerParent?.querySelector('.chat-blocked-message');
		
		if (isBlocked) {
			// Hide the composer form
			ui.composerForm.style.display = 'none';
			
			if (!blockedMsg) {
				blockedMsg = document.createElement('div');
				blockedMsg.className = 'chat-blocked-message';
				blockedMsg.style.cssText = `
					background: #fef2f2;
					border: 1px solid #dc2626;
					border-radius: 8px;
					padding: 12px 16px;
					color: #7f1d1d;
					font-size: 0.9375rem;
					text-align: center;
					font-weight: 500;
					line-height: 1.4;
					margin: 0 20px 20px 20px; /* Thêm margin cho đẹp giống ảnh */
				`;
				composerParent?.appendChild(blockedMsg);
			}
			
			// 👇 SỬA LẠI ĐOẠN TEXT NÀY CHO CHUẨN VỚI ẢNH 👇
			if (iBlockedThem || state.blockedUserIds.has(Number(peerId || 0))) {
                // Mình chặn người ta (qua chat hoặc qua settings)
				blockedMsg.textContent = 'Bạn đã chặn người dùng này. Không thể nhắn tin.';
			} else {
                // Người ta chặn mình
				blockedMsg.textContent = `Bạn đã bị ${conversation?.peer?.username || 'người dùng này'} chặn. Không thể nhắn tin.`;
			}
			
			blockedMsg.style.display = 'block';
		} else {
			// Show the composer form
			ui.composerForm.style.display = 'grid';
			
			if (blockedMsg) {
				blockedMsg.style.display = 'none';
			}
		}
	}

	function subscribeMessages(conversationId) {
		if (state.unsubMessages) {
			state.unsubMessages();
		}

		state.messageCache = [];
		state.isLoadingMoreMessages = false;
		state.hasMoreMessages = true;

		const conversation = state.conversationsMap.get(conversationId);
		const deletedAt = conversation?.deletedFor?.[String(state.me.id)];
		
		let q;
		if (deletedAt) {
			// Chỉ fetch tin nhắn sau thời điểm xóa
			q = query(
				collection(state.db, 'conversations', conversationId, 'messages'),
				where('createdAt', '>', deletedAt),
				orderBy('createdAt', 'desc'),
				limit(20),
			);
		} else {
			// Fetch toàn bộ tin nhắn
			q = query(
				collection(state.db, 'conversations', conversationId, 'messages'),
				orderBy('createdAt', 'desc'),
				limit(20),
			);
		}

		state.unsubMessages = onSnapshot(q, (snapshot) => {
			const rows = [];
			snapshot.forEach((item) => rows.push({ id: item.id, ...item.data() }));
			state.messageCache = rows.reverse();
			
			renderMessagesWithDeletionFilter();
			setupMessageScrollListener();
		});
	}

	function renderMessagesWithDeletionFilter() {
		const conversation = state.conversationsMap.get(state.activeConversationId);
		const deletedAt = conversation?.deletedFor?.[String(state.me.id)];
		
		// Filter messages: only show messages created after deletion
		const filteredMessages = deletedAt 
			? state.messageCache.filter(msg => toMillis(msg.createdAt) > toMillis(deletedAt))
			: state.messageCache;
		
		renderMessages(filteredMessages);
	}

	function setupMessageScrollListener() {
		if (!ui.messageList) {
			return;
		}

		// Remove old listener
		const oldListener = ui.messageList._scrollListener;
		if (oldListener) {
			ui.messageList.removeEventListener('scroll', oldListener);
		}

		const scrollListener = debounce(async () => {
			if (state.isLoadingMoreMessages || !state.hasMoreMessages) {
				return;
			}

			// Check if scrolled to top
			if (ui.messageList.scrollTop < 100) {
				await loadMoreMessages();
			}
		}, 300);

		ui.messageList.addEventListener('scroll', scrollListener);
		ui.messageList._scrollListener = scrollListener;
	}

	async function loadMoreMessages() {
		if (!state.activeConversationId || state.messageCache.length === 0) {
			return;
		}

		state.isLoadingMoreMessages = true;
		const oldestMessage = state.messageCache[0];
		const oldestTimestamp = oldestMessage.createdAt;
		const conversation = state.conversationsMap.get(state.activeConversationId);
		const deletedAt = conversation?.deletedFor?.[String(state.me.id)];

		try {
			let q;
			if (deletedAt) {
				// Chỉ load tin nhắn sau thời điểm xóa
				q = query(
					collection(state.db, 'conversations', state.activeConversationId, 'messages'),
					where('createdAt', '<', oldestTimestamp),
					where('createdAt', '>', deletedAt),
					orderBy('createdAt', 'desc'),
					limit(20),
				);
			} else {
				q = query(
					collection(state.db, 'conversations', state.activeConversationId, 'messages'),
					orderBy('createdAt', 'desc'),
					where('createdAt', '<', oldestTimestamp),
					limit(20),
				);
			}

			const snapshot = await getDocs(q);
			const newMessages = [];
			snapshot.forEach((item) => newMessages.push({ id: item.id, ...item.data() }));

			if (newMessages.length === 0) {
				state.hasMoreMessages = false;
				return;
			}

			newMessages.reverse();
			state.messageCache = [...newMessages, ...state.messageCache];
			
			renderMessagesWithDeletionFilter();

			// Keep scroll position
			const oldScrollHeight = ui.messageList.scrollHeight;
			setTimeout(() => {
				ui.messageList.scrollTop = ui.messageList.scrollHeight - oldScrollHeight;
			}, 0);
		} catch (error) {
			console.error('Load more messages failed', error);
		} finally {
			state.isLoadingMoreMessages = false;
		}
	}

	function renderMessages(rows) {
		// Don't render if conversation is deleted (activeConversationId is null)
		if (!state.activeConversationId) {
			return;
		}
		
		if (!rows.length) {
			ui.messageList.innerHTML = '<div class="chat-muted">Hãy gửi tin nhắn đầu tiên.</div>';
			return;
		}

		ui.messageList.innerHTML = rows.map((item) => {
			const mine = String(item.senderId) === String(state.me.id);
			const avatarName = mine ? state.me.username : (state.activePeer?.username || 'User');
			const avatarInitial = mine ? state.me.initial : (state.activePeer?.initials || '?');
			const avatarUrl = mine
				? resolveAvatarUrl(state.me.avatarSrc || state.me.avatarUrl || '')
				: resolveAvatarUrl(state.activePeer?.avatarSrc || state.activePeer?.avatarUrl || '');
			const avatarStyle = avatarStyleByName(avatarName);
			const avatarHtml = avatarUrl
				? `<span class="chat-message-avatar" style="position:relative; overflow:hidden; background:transparent; color:transparent;">
						<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(avatarName)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.style.display='none'; this.parentElement.style.background='${avatarStyle.match(/background:([^;]+)/)?.[1] || '#1a6291'}'; this.parentElement.style.color='${avatarStyle.match(/color:([^;]+)/)?.[1] || '#fff'}'; this.parentElement.textContent='${escapeHtml(avatarInitial)}';">
				   </span>`
				: `<span class="chat-message-avatar" style="${avatarStyle}">${escapeHtml(avatarInitial)}</span>`;
			let messageContent = '';

			if (item.type === 'attachment') {
				const fileType = (item.fileType || '').toLowerCase();
				const fileName = item.fileName || 'Tệp đính kèm';
				const ext = fileName.split('.').pop()?.toLowerCase() || '';
				const isImage = fileType.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
				const isVideo = fileType.startsWith('video/') || ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv'].includes(ext);

				if (isImage) {
					messageContent = `<img src="${escapeHtml(item.fileUrl || '')}" alt="${escapeHtml(fileName)}" class="chat-attachment-image" data-viewer-image="${escapeHtml(item.fileUrl || '')}" style="cursor: pointer;">`;
				} else if (isVideo) {
					messageContent = `<video controls class="chat-attachment-video"><source src="${escapeHtml(item.fileUrl || '')}" type="${escapeHtml(fileType)}">Video không được hỗ trợ</video>`;
				} else {
					messageContent = `<a href="${escapeHtml(item.fileUrl || '#')}" target="_blank" rel="noopener noreferrer" class="chat-attachment-link">📎 ${escapeHtml(fileName)}</a>`;
				}
			} else {
				messageContent = `<span>${escapeHtml(item.text || '')}</span>`;
			}

			return `
				<div class="chat-message-row ${mine ? 'mine' : 'their'}">
					${avatarHtml}
					<div class="chat-message-body">
						<div class="chat-bubble ${mine ? 'mine' : 'their'}">${messageContent}</div>
						<div class="chat-time">${escapeHtml(formatShortTime(item.createdAt))}</div>
					</div>
				</div>
			`;
		}).join('');

		ui.messageList.scrollTop = ui.messageList.scrollHeight;
		
		// Attach event listeners for image viewer
		ui.messageList.querySelectorAll('[data-viewer-image]').forEach((img) => {
			img.addEventListener('click', () => {
				const imageUrl = img.dataset.viewerImage;
				const viewer = document.getElementById('chatImageViewer');
				const viewerImg = document.getElementById('chatImageViewerImg');
				if (viewer && viewerImg) {
					viewerImg.src = imageUrl;
					viewer.style.display = 'flex';
				}
			});
		});
	}

	function subscribeAttachments(conversationId) {
		if (state.unsubAttachments) {
			state.unsubAttachments();
		}

		const q = query(
			collection(state.db, 'conversations', conversationId, 'attachments'),
			orderBy('createdAt', 'desc'),
			limit(40),
		);

		state.unsubAttachments = onSnapshot(q, (snapshot) => {
			const rows = [];
			snapshot.forEach((item) => rows.push({ id: item.id, ...item.data() }));
			renderAttachmentList(rows);
		});
	}

	function renderAttachmentList(rows) {
		ui.attachmentCount.textContent = String(rows.length);

		if (!rows.length) {
			ui.attachmentList.innerHTML = '<div class="chat-muted">Chưa có tệp đính kèm.</div>';
			return;
		}

		ui.attachmentList.innerHTML = rows.map((item) => {
			const ext = (item.fileName || '').split('.').pop()?.toLowerCase() || 'file';
			return `
				<a class="chat-attachment-item" href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener noreferrer">
					<div>
						<strong>${escapeHtml(item.fileName || 'Tệp')}</strong>
						<small>${escapeHtml(formatBytes(item.size || 0))} · ${escapeHtml(formatShortTime(item.createdAt))}</small>
					</div>
					<span class="chat-attachment-ext">.${escapeHtml(ext)}</span>
				</a>
			`;
		}).join('');
	}

	async function sendTextMessage() {
		const text = ui.textInput.value.trim();
		if (!text || !state.activeConversationId) {
			return;
		}

		const conversation = state.conversationsMap.get(state.activeConversationId);
		if (conversation && isConversationBlocked(conversation)) {
			const myId = String(state.me.id);
			const blockedBy = conversation.blockedBy || [];
			const iBlockedThem = blockedBy.includes(myId);
			
			if (iBlockedThem) {
				toast('Bạn đã chặn người dùng này.');
			} else {
				toast(`Bạn đã bị ${conversation.peer?.username || 'người dùng này'} chặn.`);
			}
			return;
		}

		ui.sendBtn.disabled = true;
		try {
			await addDoc(collection(state.db, 'conversations', state.activeConversationId, 'messages'), {
				senderId: String(state.me.id),
				text,
				type: 'text',
				createdAt: serverTimestamp(),
			});

			await updateConversationMetaOnSend(state.activeConversationId, {
				lastMessageText: text,
				lastMessageType: 'text',
			});

			ui.textInput.value = '';
			setOwnPresence(true);
		} finally {
			ui.sendBtn.disabled = false;
		}
	}

	async function sendAttachmentMessages() {
		if (!state.activeConversationId) {
			return;
		}

		const files = Array.from(ui.fileInput.files || []);
		if (!files.length) {
			return;
		}

		const conversation = state.conversationsMap.get(state.activeConversationId);
		if (conversation && isConversationBlocked(conversation)) {
			const myId = String(state.me.id);
			const blockedBy = conversation.blockedBy || [];
			const iBlockedThem = blockedBy.includes(myId);
			
			if (iBlockedThem) {
				toast('Bạn đã chặn người dùng này.');
			} else {
				toast(`Bạn đã bị ${conversation.peer?.username || 'người dùng này'} chặn.`);
			}
			return;
		}

		ui.fileBtn.disabled = true;

		try {
			for (const file of files) {
				const uploaded = await uploadAttachmentViaApi(file);

				await addDoc(collection(state.db, 'conversations', state.activeConversationId, 'messages'), {
					senderId: String(state.me.id),
					type: 'attachment',
					text: '',
					fileName: uploaded.fileName,
					fileSize: uploaded.size,
					fileType: uploaded.contentType || '',
					fileUrl: uploaded.url,
					storagePath: uploaded.storagePath,
					createdAt: serverTimestamp(),
				});

				await addDoc(collection(state.db, 'conversations', state.activeConversationId, 'attachments'), {
					senderId: String(state.me.id),
					fileName: uploaded.fileName,
					size: uploaded.size,
					contentType: uploaded.contentType || '',
					url: uploaded.url,
					storagePath: uploaded.storagePath,
					createdAt: serverTimestamp(),
				});

				await updateConversationMetaOnSend(state.activeConversationId, {
					lastMessageText: `📎 ${uploaded.fileName}`,
					lastMessageType: 'attachment',
				});
			}
		} catch (error) {
			console.error(error);
			toast(error.message || 'Tải tệp lên thất bại.');
		} finally {
			ui.fileBtn.disabled = false;
			setOwnPresence(true);
		}
	}

	async function uploadAttachmentViaApi(file) {
		const formData = new FormData();
		formData.append('_csrf', state.csrfToken);
		formData.append('file', file, file.name);

		const response = await fetch(`${state.baseUrl}/chat-api/upload`, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});

		const json = await response.json().catch(() => ({}));
		if (!response.ok || !json?.item) {
			throw new Error(json.error || 'Upload failed');
		}

		return json.item;
	}

	async function updateConversationMeta(conversationId, fields) {
		const convRef = doc(state.db, 'conversations', conversationId);

		await updateDoc(convRef, {
			...fields,
			lastSenderId: String(state.me.id),
			updatedAt: serverTimestamp(),
		});
	}

	async function updateConversationMetaOnSend(conversationId, fields) {
		const convRef = doc(state.db, 'conversations', conversationId);

		// Get current conversation to preserve deletedFor field
		const convSnapshot = await getDoc(convRef);
		const currentData = convSnapshot.data() || {};

		await updateDoc(convRef, {
			...fields,
			lastSenderId: Number(state.me.id), // Use number type for consistency
			updatedAt: serverTimestamp(),
			// Explicitly preserve deletedFor to ensure conversation reappears if hidden was deleted
			...(currentData.deletedFor && { deletedFor: currentData.deletedFor }),
		});
	}

	async function deleteConversationFromInbox() {
		if (!state.activeConversationId) {
			return;
		}

		// Clear message cache and UI immediately
		state.messageCache = [];
		if (state.unsubMessages) {
			state.unsubMessages();
			state.unsubMessages = null;
		}

		const deletedConvId = state.activeConversationId;
		
		await updateDoc(doc(state.db, 'conversations', deletedConvId), {
			[`deletedFor.${String(state.me.id)}`]: serverTimestamp(),
		});

		state.activeConversationId = null;
		state.activePeer = null;
		toggleEmptyState(true);
		ui.messageList.innerHTML = '';
		syncHeaderAndDetails(null);
		setDetailPanelOpen(false);
		
		// Update conversation in map with new deletedFor timestamp
		const conv = state.conversationsMap.get(deletedConvId);
		if (conv) {
			conv.deletedFor = conv.deletedFor || {};
			conv.deletedFor[String(state.me.id)] = new Date();
		}
		
		// Re-render to hide the deleted conversation
		renderConversationList(ui.searchInput.value.trim().toLowerCase());
	}

	async function toggleBlockUser() {
		if (!state.activeConversationId) {
			return;
		}

		const conversation = state.conversationsMap.get(state.activeConversationId);
		if (!conversation) {
			return;
		}

		const myId = String(state.me.id);
		const blockedBy = conversation.blockedBy || [];
		const isBlocked = blockedBy.includes(myId);

		// Only update blockedBy field, don't update updatedAt to prevent notification creation
		await updateDoc(doc(state.db, 'conversations', state.activeConversationId), {
			blockedBy: isBlocked ? arrayRemove(myId) : arrayUnion(myId),
		});
	}

	function normalizeConversation(id, data) {
		const participantMeta = data.participantMeta || {};
		const myId = String(state.me.id);
		const peerKey = Object.keys(participantMeta).find((key) => {
			const keyNum = parseAppUserId(key);
			return key !== myId && keyNum !== state.me.id;
		}) || '';

		const participantIds = Array.isArray(data.participants) ? data.participants : [];
		const peerIdFromParticipants = participantIds
			.map((v) => parseAppUserId(v))
			.find((v) => v > 0 && v !== state.me.id) || 0;

		const participantKeys = Array.isArray(data.participantKeys) ? data.participantKeys : [];
		const peerIdFromParticipantKeys = participantKeys
			.map((v) => parseAppUserId(v))
			.find((v) => v > 0 && v !== state.me.id) || 0;

		const peerIdFromConversationId = parsePeerIdFromConversationId(id, state.me.id);

		const peerIdFromKey = parseAppUserId(peerKey);
		const peer = participantMeta[peerKey]
			|| participantMeta[String(peerIdFromParticipants)]
			|| participantMeta[String(peerIdFromParticipantKeys)]
			|| participantMeta[`app_${peerIdFromParticipants}`]
			|| participantMeta[`app_${peerIdFromParticipantKeys}`]
			|| {
			username: 'Người dùng',
			email: '',
			initials: '?',
		};
		const peerNumericId = parseAppUserId(peer.id)
			|| peerIdFromKey
			|| peerIdFromParticipants
			|| peerIdFromParticipantKeys
			|| peerIdFromConversationId;
		const cached = state.peerMetaCache.get(peerNumericId) || null;

		return {
			id,
			participants: data.participants || [],
			blockedBy: data.blockedBy || [],
			deletedFor: data.deletedFor || {},
			videoCall: data.videoCall || null,
			isRead: data.isRead || {},
			readAt: data.readAt || {},
			lastSenderId: data.lastSenderId,
			lastMessageText: data.lastMessageText || '',
			updatedAt: data.updatedAt,
			peer: {
				id: peerNumericId,
				username: peer.username || cached?.username || 'Người dùng',
				email: peer.email || cached?.email || '',
				initials: peer.initials || cached?.initials || ((peer.username || cached?.username || 'U').charAt(0).toUpperCase()),
				avatarUrl: peer.avatarUrl || peer.avatar_url || cached?.avatarUrl || '',
				avatarSrc: peer.avatarSrc || peer.avatar_src || cached?.avatarSrc || '',
			},
		};
	}

	async function ensurePeerMeta(peerId) {
		const id = Number(peerId || 0);
		if (id <= 0) return;
		if (state.peerMetaCache.has(id)) return;
		if (state.peerMetaPending.has(id)) return;

		state.peerMetaPending.add(id);
		try {
			const data = await apiGet(`/chat-api/users/${id}`);
			const item = data?.item;
			if (!item) return;
			state.peerMetaCache.set(id, item);

			let mutated = false;
			state.conversations = state.conversations.map((conv) => {
				if (Number(conv?.peer?.id || 0) !== id) return conv;
				mutated = true;
				return {
					...conv,
					peer: {
						...conv.peer,
						username: item.username || conv.peer.username,
						email: item.email || conv.peer.email,
						initials: item.initials || conv.peer.initials,
						avatarUrl: item.avatarUrl || conv.peer.avatarUrl,
						avatarSrc: item.avatarSrc || conv.peer.avatarSrc,
					},
				};
			});

			if (mutated) {
				state.conversationsMap = new Map(state.conversations.map((entry) => [entry.id, entry]));
				renderConversationList(ui.searchInput.value.trim().toLowerCase());
				if (state.activeConversationId && state.conversationsMap.has(state.activeConversationId)) {
					syncHeaderAndDetails(state.conversationsMap.get(state.activeConversationId));
				}
			}
		} catch (error) {
			// Ignore; fallback initials remains visible
		} finally {
			state.peerMetaPending.delete(id);
		}
	}

	async function ensurePeerMetaByUsername(username) {
		const uname = String(username || '').trim();
		if (!uname || uname.toLowerCase() === 'người dùng') return;

		const key = uname.toLowerCase();
		if (state.peerMetaNamePending.has(key)) return;

		state.peerMetaNamePending.add(key);
		try {
			const data = await apiGet(`/chat-api/users?q=${encodeURIComponent(uname)}&limit=20`);
			const items = Array.isArray(data?.items) ? data.items : [];
			const exact = items.find((u) => String(u?.username || '').toLowerCase() === key) || null;
			if (!exact) return;

			const id = Number(exact.id || 0);
			if (id > 0) {
				state.peerMetaCache.set(id, exact);
			}

			let mutated = false;
			state.conversations = state.conversations.map((conv) => {
				const convUser = String(conv?.peer?.username || '').toLowerCase();
				if (convUser !== key) return conv;
				mutated = true;
				return {
					...conv,
					peer: {
						...conv.peer,
						id: Number(conv.peer?.id || 0) > 0 ? conv.peer.id : id,
						username: exact.username || conv.peer.username,
						email: exact.email || conv.peer.email,
						initials: exact.initials || conv.peer.initials,
						avatarUrl: exact.avatarUrl || conv.peer.avatarUrl,
						avatarSrc: exact.avatarSrc || conv.peer.avatarSrc,
					},
				};
			});

			if (mutated) {
				state.conversationsMap = new Map(state.conversations.map((entry) => [entry.id, entry]));
				renderConversationList(ui.searchInput.value.trim().toLowerCase());
				if (state.activeConversationId && state.conversationsMap.has(state.activeConversationId)) {
					syncHeaderAndDetails(state.conversationsMap.get(state.activeConversationId));
				}
			}
		} catch (error) {
			// Keep fallback initials
		} finally {
			state.peerMetaNamePending.delete(key);
		}
	}

	function resolveAvatarUrl(rawValue) {
		const raw = String(rawValue || '').trim();
		if (!raw) return '';
		if (/^https?:\/\//i.test(raw)) return raw;
		const base = String(state.baseUrl || '').replace(/\/$/, '');
		if (base && raw.startsWith(base + '/')) return raw;
		if (raw.startsWith('/')) return `${base}${raw}`;

		const normalized = raw.replace(/\\/g, '/').replace(/^\/+/, '');
		if (/^(avatars|posts|chat)\//i.test(normalized)) {
			return `${base}/media/view?key=${encodeURIComponent(normalized)}`;
		}
		if (/^media\//i.test(normalized)) {
			return `${base}/public/${normalized}`;
		}
		if (/^public\//i.test(normalized)) {
			return `${base}/${normalized}`;
		}
		return `${base}/public/media/${normalized}`;
	}

	function setAvatarFallback(el, name, initials) {
		if (!el) return;
		const color = avatarColorByName(name || 'User');
		el.style.background = color.bg;
		el.style.color = color.fg;
		el.textContent = initials || '?';
	}

	function setAvatarElement(el, { name, initials, url }) {
		if (!el) return;
		const safeInitials = String(initials || '?').charAt(0).toUpperCase() || '?';
		const resolved = resolveAvatarUrl(url);

		el.innerHTML = '';
		if (!resolved) {
			setAvatarFallback(el, name, safeInitials);
			return;
		}

		const img = document.createElement('img');
		img.src = resolved;
		img.alt = String(name || 'User');
		img.style.width = '100%';
		img.style.height = '100%';
		img.style.objectFit = 'cover';
		img.style.borderRadius = '50%';

		img.addEventListener('error', () => {
			el.innerHTML = '';
			setAvatarFallback(el, name, safeInitials);
		});

		el.style.background = 'transparent';
		el.style.color = 'transparent';
		el.appendChild(img);
	}

	function isHiddenByDelete(conversation) {
		if (!conversation) return true;
		
		const myId = String(state.me.id);
		const deletedAt = conversation.deletedFor?.[myId];
		
		if (!deletedAt) {
			return false; // Not deleted
		}

		// Hide only if deleted and no new messages after deletion
		const lastMessageTime = toMillis(conversation.updatedAt);
		const deletedTime = toMillis(deletedAt);
		
		// Safely compare - if either is 0 or negative, don't hide
		if (lastMessageTime <= 0 || deletedTime <= 0) {
			console.warn('[isHiddenByDelete] Invalid timestamps for', conversation.id, { lastMessageTime, deletedTime });
			return false; // Can't determine, show it
		}
		
		return lastMessageTime <= deletedTime;
	}

	function isConversationBlocked(conversation) {
		const blockedBy = conversation.blockedBy || [];
		const myId = String(state.me.id);
		const peerId = String(conversation.peer?.id || '');

		return blockedBy.includes(myId) || blockedBy.includes(peerId) || isUserBlockedRelation(Number(peerId || 0));
	}

	function isUserBlockedRelation(userId) {
		const n = Number(userId || 0);
		if (!n) return false;
		return state.blockedUserIds.has(n) || state.blockedByUserIds.has(n);
	}

	function toggleEmptyState(show) {
		ui.emptyState.classList.toggle('show', show);
	}

	function setDetailPanelOpen(isOpen) {
		ui.root.classList.toggle('chat-detail-open', isOpen);
	}

	function avatarStyleByName(name) {
		const color = avatarColorByName(name);
		return `background:${color.bg};color:${color.fg};`;
	}

	function parseAppUserId(value) {
		const s = String(value ?? '').trim();
		if (!s) return 0;
		if (/^\d+$/.test(s)) return Number(s);
		const m = s.match(/^app_(\d+)$/i);
		if (m) return Number(m[1]);
		return 0;
	}

	function parsePeerIdFromConversationId(conversationId, myId) {
		const s = String(conversationId || '').trim();
		const m = s.match(/^conv_(\d+)_(\d+)$/i);
		if (!m) return 0;
		const a = Number(m[1]);
		const b = Number(m[2]);
		if (!Number.isFinite(a) || !Number.isFinite(b)) return 0;
		const mine = Number(myId || 0);
		if (a === mine && b > 0) return b;
		if (b === mine && a > 0) return a;
		return 0;
	}

	function avatarColorByName(name) {
		const palette = [
			{ bg: '#E6F4FF', fg: '#005B96' },
			{ bg: '#E8F8F5', fg: '#0F766E' },
			{ bg: '#FFF4E5', fg: '#B45309' },
			{ bg: '#F3E8FF', fg: '#7E22CE' },
			{ bg: '#FFECEE', fg: '#BE123C' },
			{ bg: '#EAF2FF', fg: '#1D4ED8' },
		];

		const key = String(name || 'user').trim().toLowerCase();
		let hash = 0;
		for (let i = 0; i < key.length; i += 1) {
			hash = ((hash << 5) - hash) + key.charCodeAt(i);
			hash |= 0;
		}

		const idx = Math.abs(hash) % palette.length;
		return palette[idx];
	}

	function buildConversationId(a, b) {
		const [x, y] = [Number(a), Number(b)].sort((m, n) => m - n);
		return `conv_${x}_${y}`;
	}

	function normalizeIceServers(raw) {
		if (!Array.isArray(raw) || raw.length === 0) {
			return [{ urls: 'stun:stun.l.google.com:19302' }];
		}

		const sanitized = raw
			.map((entry) => {
				const urls = Array.isArray(entry?.urls)
					? entry.urls.filter(Boolean)
					: (entry?.urls ? [entry.urls] : []);

				if (urls.length === 0) return null;

				const out = { urls: urls.length === 1 ? urls[0] : urls };
				if (entry?.username) out.username = String(entry.username);
				if (entry?.credential) out.credential = String(entry.credential);
				return out;
			})
			.filter(Boolean);

		return sanitized.length > 0 ? sanitized : [{ urls: 'stun:stun.l.google.com:19302' }];
	}

	async function apiGet(path) {
		const response = await fetch(`${state.baseUrl}${path}`, {
			headers: {
				Accept: 'application/json',
			},
			credentials: 'same-origin',
		});

		const json = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw new Error(json.error || 'Request failed');
		}

		return json;
	}

	function escapeHtml(input) {
		return String(input ?? '')
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;');
	}

	function formatShortTime(timestamp) {
		if (!timestamp) {
			return '';
		}

		const date = typeof timestamp.toDate === 'function'
			? timestamp.toDate()
			: new Date(toMillis(timestamp));

		if (Number.isNaN(date.getTime())) {
			return '';
		}

		return new Intl.DateTimeFormat('vi-VN', {
			hour: '2-digit',
			minute: '2-digit',
		}).format(date);
	}

	function formatBytes(bytes) {
		if (!bytes || bytes <= 0) {
			return '0 B';
		}

		const units = ['B', 'KB', 'MB', 'GB'];
		const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
		const value = bytes / (1024 ** power);

		return `${value.toFixed(power === 0 ? 0 : 1)} ${units[power]}`;
	}

	function toMillis(value) {
		if (!value) {
			return 0;
		}

		if (typeof value.toMillis === 'function') {
			return value.toMillis();
		}

		if (typeof value.seconds === 'number') {
			return value.seconds * 1000;
		}

		if (value instanceof Date) {
			return value.getTime();
		}

		const parsed = Date.parse(value);
		return Number.isNaN(parsed) ? 0 : parsed;
	}

	function debounce(fn, wait = 250) {
		let timer = null;
		return (...args) => {
			if (timer) {
				window.clearTimeout(timer);
			}
			timer = window.setTimeout(() => fn(...args), wait);
		};
	}

	function toast(message) {
		// Lightweight non-blocking fallback
		window.alert(message);
	}
}
