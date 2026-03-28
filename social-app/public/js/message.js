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
		baseUrl: root.dataset.baseUrl || '',
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
		detailName: document.getElementById('chatDetailName'),
		detailHandle: document.getElementById('chatDetailHandle'),
		detailStatus: document.getElementById('chatDetailStatus'),
		detailUser: document.getElementById('chatDetailUser'),
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
		newConversationBtn: document.getElementById('chatNewConversationBtn'),
	};

	const deleteModal = new bootstrap.Modal('#chatDeleteModal');

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

		state.firebase = initializeApp(bootstrapData.firebase);
		state.auth = getAuth(state.firebase);
		state.db = getFirestore(state.firebase);

		await signInWithCustomToken(state.auth, bootstrapData.customToken);
		setupPresence();
		await subscribeConversations();

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

	// Empty state button
	const emptyStateBtn = document.getElementById('chatEmptyStateBtn');
	if (emptyStateBtn) {
		emptyStateBtn.addEventListener('click', () => {
			const modal = new bootstrap.Modal('#chatNewConversationModal');
			modal.show();
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
					return `
						<div class="d-flex align-items-center gap-2 p-2 border-bottom chat-user-suggestion" data-user-id="${user.id}">
							<div style="width: 40px; height: 40px; border-radius: 50%; background: ${avatarColor.bg}; color: ${avatarColor.fg}; display: flex; align-items: center; justify-content: center; font-weight: 600;">
								${user.initials}
							</div>
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
			return `
				<button type="button" class="chat-conversation-item ${isActive ? 'active' : ''}" data-conversation-id="${item.id}">
					<div class="chat-conversation-avatar" style="${avatarStyle}">${escapeHtml(item.peer.initials)}</div>
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
		if (state.searchUsers.length === 0) {
			ui.searchSuggestions.classList.add('d-none');
			ui.searchSuggestions.innerHTML = '';
			return;
		}

		ui.searchSuggestions.classList.remove('d-none');
		ui.searchSuggestions.innerHTML = state.searchUsers.map((item) => `
			<button type="button" class="chat-suggestion-item" data-user-id="${item.id}">
				<span class="chat-conversation-avatar" style="${avatarStyleByName(item.username)}">${escapeHtml(item.initials)}</span>
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

		const data = await apiGet(`/chat-api/users/${userId}`);
		const user = data?.item;
		if (!user) {
			return;
		}

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
					avatarUrl: '',
					initials: state.me.initial,
				},
				[String(user.id)]: {
					id: user.id,
					username: user.username,
					email: user.email,
					avatarUrl: user.avatarUrl || '',
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
			ui.headerAvatar.removeAttribute('style');
			const detailAvatar = ui.detailUser.querySelector('.chat-user-avatar');
			if (detailAvatar) {
				detailAvatar.textContent = '?';
				detailAvatar.removeAttribute('style');
			}
			state.activePeer = null;
			subscribePeerPresence(0);
			updateComposerBlockedState(null);
			return;
		}

		state.activePeer = conversation.peer;

		ui.headerName.textContent = conversation.peer.username;
		ui.headerAvatar.textContent = conversation.peer.initials;
		ui.headerAvatar.setAttribute('style', avatarStyleByName(conversation.peer.username));

		ui.detailName.textContent = conversation.peer.username;
		ui.detailHandle.textContent = conversation.peer.email ? `@${conversation.peer.email}` : '';
		const detailAvatar = ui.detailUser.querySelector('.chat-user-avatar');
		if (detailAvatar) {
			detailAvatar.textContent = conversation.peer.initials;
			detailAvatar.setAttribute('style', avatarStyleByName(conversation.peer.username));
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
		
		updateComposerBlockedState(conversation);
	}

	function updateComposerBlockedState(conversation) {
		const myId = String(state.me.id);
		const peerId = conversation ? String(conversation.peer?.id || '') : '';
		const blockedBy = conversation?.blockedBy || [];
		
		// Check if I blocked them or if they blocked me
		const iBlockedThem = blockedBy.includes(myId);
		const theyBlockedMe = blockedBy.includes(peerId);
		const isBlocked = iBlockedThem || theyBlockedMe;
		
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
				`;
				composerParent?.appendChild(blockedMsg);
			}
			
			// Update message text
			if (iBlockedThem) {
				blockedMsg.textContent = 'Bạn đã chặn người dùng này. Không thể nhắn tin.';
			} else {
				blockedMsg.textContent = `Bạn đã bị ${conversation?.peer?.username || 'người dùng này'} chặn.`;
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
					<div class="chat-message-avatar" style="${avatarStyleByName(avatarName)}">${mine ? escapeHtml(state.me.initial) : escapeHtml(state.activePeer?.initials || '?')}</div>
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
		const peerId = Object.keys(participantMeta).find((key) => key !== myId) || '';
		const peer = participantMeta[peerId] || {
			username: 'Người dùng',
			email: '',
			initials: '?',
		};

		return {
			id,
			participants: data.participants || [],
			blockedBy: data.blockedBy || [],
			deletedFor: data.deletedFor || {},
			isRead: data.isRead || {},
			readAt: data.readAt || {},
			lastSenderId: data.lastSenderId,
			lastMessageText: data.lastMessageText || '',
			updatedAt: data.updatedAt,
			peer: {
				id: Number(peer.id || peerId || 0),
				username: peer.username || 'Người dùng',
				email: peer.email || '',
				initials: peer.initials || ((peer.username || 'U').charAt(0).toUpperCase()),
				avatarUrl: peer.avatarUrl || '',
			},
		};
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

		return blockedBy.includes(myId) || blockedBy.includes(peerId);
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

