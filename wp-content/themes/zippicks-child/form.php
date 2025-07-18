<?php
/**
 * Template Name: Register Form
 */
get_header();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $errors = zippicks_handle_registration();
}
?>

<style>
  body { font-family: system-ui, sans-serif; background-color: #f3f4f6; }
  .zip-container {
    max-width: 720px;
    margin: 3rem auto;
    padding: 3rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.07);
  }
  .zip-section-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #1e40af;
  }
  .zip-muted {
    font-size: 1.1rem;
    color: #4b5563;
    margin-bottom: 2rem;
  }
  .zip-registration-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }
  .zip-registration-form input,
  .zip-registration-form select {
    padding: 0.85rem;
    font-size: 1rem;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    transition: border-color 0.2s ease;
  }
  .zip-registration-form input:focus,
  .zip-registration-form select:focus {
    outline: none;
    border-color: #1e40af;
    box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.2);
  }
  .zip-field small {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
    display: block;
  }
  .zip-terms-row {
    font-size: 0.95rem;
    line-height: 1.5;
    display: flex;
    justify-content: flex-start;
  }
  .zip-terms-row label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
  }
  .zip-terms-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
  }
  .zip-terms-row a {
    color: #1e40af;
    text-decoration: underline;
    white-space: nowrap;
  }
  @media (max-width: 768px) {
    .zip-terms-row label {
      flex-direction: column;
      align-items: flex-start;
    }
  }
  .zip-form-actions {
    margin-top: 2rem;
  }
  .zip-follow-button {
    background-color: #1e40af;
    color: white;
    font-weight: 700;
    padding: 0.85rem 2rem;
    font-size: 1.05rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s ease;
  }
  .zip-follow-button:hover:not(:disabled) {
    background-color: #1c3ea0;
  }
  .zip-follow-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .zip-error-messages {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 1.25rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    margin-bottom: 2rem;
  }
  .zip-error-messages ul {
    padding-left: 1.25rem;
    margin: 0;
  }
  .zip-error-messages li {
    margin-bottom: 0.4rem;
    list-style: disc;
  }
  .password-meter {
    height: 6px;
    width: 100%;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.4rem;
  }
  .password-meter-fill {
    height: 100%;
    width: 0;
    transition: width 0.3s ease;
  }
</style>

<div class="zip-container zip-main-column">
  <h1 class="zip-section-title">Create Your Account</h1>
  <p class="zip-muted">Join ZipPicks and discover top-rated restaurants, bars, and hidden gems curated by experts and tastemakers nationwide.</p>

  <?php if (!empty($errors)): ?>
    <div class="zip-error-messages">
      <ul><?php foreach ($errors as $e) echo "<li>" . esc_html($e) . "</li>"; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="" class="zip-registration-form" novalidate>
    <?php wp_nonce_field('zippicks_user_register', 'zippicks_register_nonce'); ?>

    <input type="text" name="first_name" placeholder="First Name" required autofocus>
    <input type="text" name="last_name" placeholder="Last Name" required>

    <select name="user_role" required>
      <option value="">Select Role</option>
      <option value="zipper">Zipper</option>
      <option value="critic">Critic</option>
      <option value="business_owner">Business Owner</option>
    </select>
    <small>Zippers are approved instantly. Critics & Businesses require review and approval.</small>

    <input type="text" name="zip_code" placeholder="ZIP Code" required>
    <small>Used to personalize your local experience.</small>

    <input type="email" name="user_email" id="user_email" placeholder="Email" required>
    <small>Your login email and verification link will be sent here.</small>
    <small id="emailSuggestion" style="color: #1e40af; display:none; font-weight: 500;"></small>

    <input type="password" name="user_password" id="user_password" placeholder="Password" required>
    <small>Use at least 8 characters with upper/lowercase, numbers, and symbols.</small>
    <div class="password-meter"><div id="passwordStrength" class="password-meter-fill"></div></div>

    <input type="password" name="confirm_password" placeholder="Confirm Password" required>

    <div class="zip-terms-row">
      <label for="terms_agree">
        <input type="checkbox" id="terms_agree" name="terms_agree" required>
        I agree to the <a href="/terms-of-use" target="_blank">Terms</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>
      </label>
    </div>

    <div class="g-recaptcha"
         data-sitekey="6Lc8sUYrAAAAAGY813swPcwC-uJy5ckmhtTECZXm"
         data-callback="onRecaptchaSuccessCallback"
         data-expired-callback="onRecaptchaExpiredCallback">
    </div>

    <div class="zip-form-actions">
      <button type="submit" class="zip-follow-button zip-btn-primary" id="submitBtn" disabled>Create Account</button>
    </div>
  </form>
</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('terms_agree');
    const submitBtn = document.getElementById('submitBtn');

    // Initialize reCAPTCHA solved state
    window.recaptchaSolved = false; // Add this line

    checkbox.addEventListener('change', () => {
        // Enable submit button only if terms are checked AND reCAPTCHA is solved
        submitBtn.disabled = !(checkbox.checked && window.recaptchaSolved);
    });

    const emailInput = document.getElementById('user_email');
    const suggestion = document.getElementById('emailSuggestion');
    const domains = ['gmail.com', 'yahoo.com', 'hotmail.com'];
    emailInput.addEventListener('blur', function () {
      const match = this.value.trim().match(/^(.*)@(.*)$/);
      if (!match) return suggestion.style.display = 'none';
      const typed = match[2].toLowerCase();
      const alt = domains.find(d => d !== typed && d.startsWith(typed[0]) && d.includes(typed.slice(1)));
      if (alt) {
        suggestion.innerHTML = `Did you mean <a href="#" onclick=\"event.preventDefault();emailInput.value='${match[1]}@${alt}';suggestion.style.display='none';\">${match[1]}@${alt}</a>?`;
        suggestion.style.display = 'block';
      } else {
        suggestion.style.display = 'none';
      }
    });

    const passwordInput = document.getElementById('user_password');
    const strengthBar = document.getElementById('passwordStrength');
    passwordInput.addEventListener('input', function () {
      const val = this.value;
      let strength = 0;
      if (val.length >= 8) strength++;
      if (/[A-Z]/.test(val)) strength++;
      if (/[a-z]/.test(val)) strength++; // Corrected: was `test(strength)`
      if (/\d/.test(val)) strength++;
      if (/[@$!%*?&]/.test(val)) strength++;
      const percent = Math.min(strength * 20, 100);
      strengthBar.style.width = percent + '%';
      strengthBar.style.backgroundColor = percent < 40 ? '#f87171' : percent < 80 ? '#facc15' : '#4ade80';
    });
  });

  // NEW reCAPTCHA callback functions
  window.onRecaptchaSuccessCallback = function(token) {
    console.log("reCAPTCHA solved:", token);
    window.recaptchaSolved = true;
    const submitBtn = document.getElementById('submitBtn');
    const checkbox = document.getElementById('terms_agree');
    // Enable submit button only if terms are also agreed
    submitBtn.disabled = !(checkbox.checked && window.recaptchaSolved);
  };

  window.onRecaptchaExpiredCallback = function() {
    console.warn("reCAPTCHA expired");
    window.recaptchaSolved = false;
    document.getElementById('submitBtn').disabled = true; // Disable if expired
  };
</script>

<?php get_footer(); ?>