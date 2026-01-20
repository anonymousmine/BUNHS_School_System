/**
* Template Name: MySchool
* Template URL: https://bootstrapmade.com/myschool-bootstrap-school-template/
* Updated: Jul 28 2025 with Bootstrap v5.3.7
* Author: BootstrapMade.com
* License: https://bootstrapmade.com/license/
*/

(function() {
  "use strict";

  /**
   * Apply .scrolled class to the body as the page is scrolled down
   */
  function toggleScrolled() {
    const selectBody = document.querySelector('body');
    const selectHeader = document.querySelector('#header');
    if (!selectHeader.classList.contains('scroll-up-sticky') && !selectHeader.classList.contains('sticky-top') && !selectHeader.classList.contains('fixed-top')) return;
    window.scrollY > 100 ? selectBody.classList.add('scrolled') : selectBody.classList.remove('scrolled');
  }

  document.addEventListener('scroll', toggleScrolled);
  window.addEventListener('load', toggleScrolled);

  /**
   * Mobile nav toggle
   */
  const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

  function mobileNavToogle() {
    document.querySelector('body').classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  }
  if (mobileNavToggleBtn) {
    mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
  }

  /**
   * Hide mobile nav on same-page/hash links
   */
  document.querySelectorAll('#navmenu a').forEach(navmenu => {
    navmenu.addEventListener('click', () => {
      if (document.querySelector('.mobile-nav-active')) {
        mobileNavToogle();
      }
    });

  });

  /**
   * Toggle mobile nav dropdowns
   */
  document.querySelectorAll('.navmenu .toggle-dropdown').forEach(navmenu => {
    navmenu.addEventListener('click', function(e) {
      e.preventDefault();
      this.parentNode.classList.toggle('active');
      this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
      e.stopImmediatePropagation();
    });
  });

  /**
   * Preloader
   */
  const preloader = document.querySelector('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove();
    });
  }

  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);

  /**
   * Init swiper sliders
   */
  function initSwiper() {
    document.querySelectorAll(".init-swiper").forEach(function(swiperElement) {
      let config = JSON.parse(
        swiperElement.querySelector(".swiper-config").innerHTML.trim()
      );

      if (swiperElement.classList.contains("swiper-tab")) {
        initSwiperWithCustomPagination(swiperElement, config);
      } else {
        new Swiper(swiperElement, config);
      }
    });
  }

  window.addEventListener("load", initSwiper);

  /**
   * Initiate Pure Counter
   */
  new PureCounter();

  /**
   * Initiate glightbox
   */
  const glightbox = GLightbox({
    selector: '.glightbox'
  });

  /**
   * Login form validation
   */
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      let isValid = true;

      // Reset error messages
      document.getElementById('usernameError').style.display = 'none';
      document.getElementById('passwordError').style.display = 'none';

      // Simple validation
      if (!username.trim()) {
        document.getElementById('usernameError').style.display = 'block';
        isValid = false;
      }

      if (!password.trim()) {
        document.getElementById('passwordError').style.display = 'block';
        isValid = false;
      }

      if (isValid) {
        // Check credentials (for demo purposes, accept admin/admin)
        if (username === 'admin' && password === 'admin') {
          alert('Login successful! Redirecting to dashboard...');
          // In a real application, you would redirect to the dashboard
          // window.location.href = 'dashboard.html';
          // Close modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
          modal.hide();
        } else {
          alert('Invalid credentials. Please try again.');
        }
      }
    });
  }

  /**
   * Signup form validation
   */
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    signupForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const firstName = document.getElementById('firstName').value;
      const lastName = document.getElementById('lastName').value;
      const email = document.getElementById('email').value;
      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const terms = document.getElementById('terms').checked;

      let isValid = true;

      // Reset error messages
      document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');

      // Validation
      if (!firstName.trim()) {
        document.getElementById('firstNameError').style.display = 'block';
        isValid = false;
      }

      if (!lastName.trim()) {
        document.getElementById('lastNameError').style.display = 'block';
        isValid = false;
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        document.getElementById('emailError').style.display = 'block';
        isValid = false;
      }

      if (!username.trim()) {
        document.getElementById('usernameError').style.display = 'block';
        isValid = false;
      }

      if (password.length < 8) {
        document.getElementById('passwordError').style.display = 'block';
        isValid = false;
      }

      if (password !== confirmPassword) {
        document.getElementById('confirmPasswordError').style.display = 'block';
        isValid = false;
      }

      if (!terms) {
        alert('Please agree to the Terms of Service and Privacy Policy');
        isValid = false;
      }

      if (isValid) {
        alert('Account created successfully! You can now log in.');
        // In a real application, you would send data to server
        // For demo, close modal and show login
        const signupModal = bootstrap.Modal.getInstance(document.getElementById('signupModal'));
        signupModal.hide();

        // Optionally open login modal
        // const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        // loginModal.show();
      }
    });
  }

})();
