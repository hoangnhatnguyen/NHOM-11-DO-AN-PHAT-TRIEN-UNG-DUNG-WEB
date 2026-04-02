/**
 * Avatar Fallback Handler
 * Shows text avatar when image fails to load
 */

function switchToTextAvatar(containerId, initial) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const img = container.querySelector('img');
    const textAvatar = container.querySelector('.avatar-text-fallback');
    
    if (img) img.classList.add('d-none');
    if (textAvatar) textAvatar.classList.remove('d-none');
}

// Initialize avatar fallbacks on page load
document.addEventListener('DOMContentLoaded', function() {
    const avatarContainers = document.querySelectorAll('[data-avatar-container]');
    
    avatarContainers.forEach(container => {
        const img = container.querySelector('img');
        const initial = container.dataset.avatarInitial || '?';
        const containerId = container.id;
        
        if (img) {
            img.addEventListener('error', function() {
                switchToTextAvatar(containerId, initial);
            });
        }
    });
});
