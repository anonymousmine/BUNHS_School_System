/**
 * School Rating System - Guest Rating Handler
 * Handles visitor ID generation, localStorage management, and AJAX submission
 */

class SchoolRatingSystem {
    constructor() {
        this.visitorId = null;
        this.hasRated = false;
        this.selectedRating = 0;
        this.init();
    }

    init() {
        this.generateOrGetVisitorId();
        this.checkIfAlreadyRated();
        this.setupEventListeners();
        this.updateUI();
    }

    // Generate or retrieve visitor ID from localStorage
    generateOrGetVisitorId() {
        let storedId = localStorage.getItem('visitor_id');
        
        if (!storedId) {
            // Generate new UUID
            storedId = crypto.randomUUID();
            localStorage.setItem('visitor_id', storedId);
        }
        
        this.visitorId = storedId;
        return storedId;
    }

    // Check if user has already rated
    checkIfAlreadyRated() {
        this.hasRated = localStorage.getItem('hasRated') === 'true';
        return this.hasRated;
    }

    // Setup event listeners
    setupEventListeners() {
        // Star rating interactions
        const stars = document.querySelectorAll('.sr-star');
        const ratingValue = document.getElementById('ratingValue');
        
        stars.forEach(star => {
            star.addEventListener('click', (e) => {
                if (this.hasRated) return;
                
                const rating = parseInt(star.dataset.rating);
                this.selectedRating = rating;
                ratingValue.value = rating;
                this.updateStarDisplay(rating);
            });

            star.addEventListener('mouseenter', (e) => {
                if (this.hasRated) return;
                
                const rating = parseInt(star.dataset.rating);
                this.updateStarHover(rating);
            });
        });

        // Reset hover on mouse leave
        const starRating = document.getElementById('starRating');
        starRating.addEventListener('mouseleave', () => {
            if (this.hasRated) return;
            this.updateStarDisplay(this.selectedRating);
        });

        // Submit button
        const submitBtn = document.getElementById('submitRating');
        if (submitBtn) {
            submitBtn.addEventListener('click', () => this.submitRating());
        }
    }

    // Update star display based on rating
    updateStarDisplay(rating) {
        const stars = document.querySelectorAll('.sr-star');
        stars.forEach((star, index) => {
            const starRating = parseInt(star.dataset.rating);
            const icon = star.querySelector('i');
            
            if (starRating <= rating) {
                icon.className = 'fas fa-star';
                star.classList.add('active');
                star.classList.remove('hover');
            } else {
                icon.className = 'far fa-star';
                star.classList.remove('active', 'hover');
            }
        });
    }

    // Update star hover effect
    updateStarHover(rating) {
        const stars = document.querySelectorAll('.sr-star');
        stars.forEach((star, index) => {
            const starRating = parseInt(star.dataset.rating);
            const icon = star.querySelector('i');
            
            if (starRating <= rating) {
                icon.className = 'fas fa-star';
                star.classList.add('hover');
            } else {
                icon.className = 'far fa-star';
                star.classList.remove('hover');
            }
        });
    }

    // Update UI based on rating status
    updateUI() {
        const formContainer = document.getElementById('rating-form-container');
        const alreadyRatedMessage = document.getElementById('alreadyRatedMessage');
        const submitBtn = document.getElementById('submitRating');
        
        if (this.hasRated) {
            // Hide form, show already rated message
            if (formContainer) formContainer.style.display = 'none';
            if (alreadyRatedMessage) alreadyRatedMessage.style.display = 'block';
        } else {
            // Show form, hide already rated message
            if (formContainer) formContainer.style.display = 'block';
            if (alreadyRatedMessage) alreadyRatedMessage.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // Submit rating via AJAX
    async submitRating() {
        if (this.hasRated) {
            this.showMessage('You have already rated our website.', 'error');
            return;
        }

        if (this.selectedRating === 0) {
            this.showMessage('Please select a rating before submitting.', 'error');
            return;
        }

        const feedback = document.getElementById('feedbackText').value.trim();
        const submitBtn = document.getElementById('submitRating');
        const btnText = submitBtn.querySelector('.sr-btn-text');
        const btnLoading = submitBtn.querySelector('.sr-btn-loading');

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';

        try {
            const response = await fetch('api/rating_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'submit_rating',
                    visitor_id: this.visitorId,
                    rating: this.selectedRating,
                    feedback: feedback
                })
            });

            const data = await response.json();

            if (data.success) {
                // Mark as rated
                this.hasRated = true;
                localStorage.setItem('hasRated', 'true');
                
                // Show success message
                this.showMessage('Thank you for your feedback!', 'success');
                
                // Update UI after delay
                setTimeout(() => {
                    this.updateUI();
                    // Optionally refresh the page to show updated ratings
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }, 1500);
                
            } else {
                this.showMessage(data.message || 'Failed to submit rating. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error submitting rating:', error);
            this.showMessage('Network error. Please try again later.', 'error');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
        }
    }

    // Show message to user
    showMessage(message, type) {
        const messageDiv = document.getElementById('ratingMessage');
        if (messageDiv) {
            messageDiv.textContent = message;
            messageDiv.className = `sr-message ${type}`;
            messageDiv.style.display = 'block';
            
            // Hide message after 5 seconds
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }
    }
}

// Initialize the rating system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new SchoolRatingSystem();
});
