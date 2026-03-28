// Follow/Unfollow functionality
document.addEventListener('DOMContentLoaded', function() {
    const followButtons = document.querySelectorAll('[data-action="follow"]');
    
    followButtons.forEach(button => {
        button.addEventListener('click', handleFollowAction);
    });
});

async function handleFollowAction(e) {
    e.preventDefault();
    
    const button = e.target;
    const userId = button.dataset.userId;
    const action = button.dataset.followAction || 'follow';
    
    if (!userId) {
        console.error('User ID not found');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('target_id', userId);
        
        const response = await fetch(`../api/follow.php?action=${action}`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            // Update button state
            if (action === 'follow') {
                button.textContent = 'Unfollow';
                button.dataset.followAction = 'unfollow';
                button.classList.remove('btn-primary');
                button.classList.add('btn-secondary');
            } else {
                button.textContent = 'Follow';
                button.dataset.followAction = 'follow';
                button.classList.remove('btn-secondary');
                button.classList.add('btn-primary');
            }
            
            // Show success message
            showNotification(data.message || 'Success', 'success');
        } else {
            showNotification(data.error || 'An error occurred', 'error');
        }
    } catch (error) {
        console.error('Follow action error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

async function checkFollowStatus(userId) {
    try {
        const response = await fetch(`../api/follow.php?action=check&target_id=${userId}`);
        const data = await response.json();
        return data.isFollowing || false;
    } catch (error) {
        console.error('Check follow status error:', error);
        return false;
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background-color: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        border-radius: 4px;
        z-index: 9999;
        max-width: 300px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
