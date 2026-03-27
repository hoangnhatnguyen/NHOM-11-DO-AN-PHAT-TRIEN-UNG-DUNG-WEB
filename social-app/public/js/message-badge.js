/**
 * Global message notification badge - works on all pages
 */
(async function initMessageBadge() {
	// Get current user from DOM - try chatApp first, then left-sidebar
	let chatApp = document.getElementById('chatApp');
	let userId = Number(chatApp?.dataset.currentUserId || 0);
	
	if (!userId) {
		const leftSidebar = document.querySelector('.left-sidebar[data-current-user-id]');
		userId = Number(leftSidebar?.dataset.currentUserId || 0);
	}
	
	if (!userId) return;
	
	let badgeUpdateTimeout = null;
	let lastBadgeState = null;
	
	try {
		// Import Firebase
		const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js');
		const { getAuth, signInWithCustomToken } = await import('https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js');
		const { getFirestore, collection, where, query, orderBy, limit, onSnapshot } = await import('https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js');
		
		// Get bootstrap data (config + token)
		const res = await fetch('/chat-api/bootstrap', {
			method: 'GET',
			headers: { 'Content-Type': 'application/json' },
		});
		const bootstrapData = await res.json();
		
		if (!bootstrapData?.firebase || !bootstrapData?.customToken) {
			return;
		}
		
		// Init Firebase
		const app = initializeApp(bootstrapData.firebase);
		const db = getFirestore(app);
		const auth = getAuth(app);
		
		// Sign in
		await signInWithCustomToken(auth, bootstrapData.customToken);
		
		// Subscribe to conversations for badge updates
		const q = query(
			collection(db, 'conversations'),
			where('participantKeys', 'array-contains', auth.currentUser.uid),
			orderBy('updatedAt', 'desc'),
			limit(50),
		);
		
		onSnapshot(q, (snapshot) => {
			// Debounce badge updates - prevent flickering
			if (badgeUpdateTimeout) clearTimeout(badgeUpdateTimeout);
			
			badgeUpdateTimeout = setTimeout(() => {
				// Check if has unread
				const conversations = [];
				snapshot.forEach((doc) => {
					conversations.push(doc.data());
				});
				
				const hasUnread = conversations.some(c => {
					const lastSenderId = c.lastSenderId;
					if (!lastSenderId || lastSenderId === userId) return false;
					
					const deletedFor = c.deletedFor || {};
					const deletedAt = deletedFor[String(userId)];
					const lastMessageTime = c.updatedAt?.toMillis?.() || 0;
					
					// Hidden by delete
					if (deletedAt && lastMessageTime <= deletedAt.toMillis?.()) return false;
					
					const readAt = c.readAt?.[String(userId)];
					const readAtTime = readAt?.toMillis?.() || 0;
					const isRead = c.isRead?.[String(userId)];
					
					// Unread: chưa đọc HOẶC tin mới hơn lần đọc cuối
					return !isRead || (lastMessageTime > readAtTime);
				});
				
				// Only update if state changed
				if (lastBadgeState === hasUnread) return;
				
				lastBadgeState = hasUnread;
				
				// Update badge in left sidebar - dùng .nav-link chứa tin nhắn
				const messageLink = document.querySelector('a[href*="/messages"]');
				
				if (messageLink) {
					let badge = messageLink.querySelector('.message-noti-badge');
					
					if (hasUnread && !badge) {
						badge = document.createElement('span');
						badge.className = 'message-noti-badge';
						messageLink.style.position = 'relative';
						messageLink.appendChild(badge);
					} else if (!hasUnread && badge) {
						badge.remove();
					}
				}
				
				// Also update chat-rail-btn (messages page sidebar)
				const chatRailBtn = document.querySelector('.chat-rail-btn[aria-label="Tin nhắn"]');
				
				if (chatRailBtn) {
					let badge = chatRailBtn.querySelector('.message-noti-badge');
					if (hasUnread && !badge) {
						badge = document.createElement('span');
						badge.className = 'message-noti-badge';
						chatRailBtn.style.position = 'relative';
						chatRailBtn.appendChild(badge);
					} else if (!hasUnread && badge) {
						badge.remove();
					}
				}
			}, 100); // Debounce 100ms
		});
	} catch (error) {
		// Silent fail
	}
})();
