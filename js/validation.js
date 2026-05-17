document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Disable default browser validation bubbles
        form.setAttribute('novalidate', true);
        
        // Add a hidden error container after every required input
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        inputs.forEach(input => {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'inline-error-msg';
            errorDiv.style.color = '#de350b';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '4px';
            errorDiv.style.display = 'none';
            errorDiv.style.fontWeight = '500';
            
            // Insert it after the input (or after its parent if it's in a password-field wrapper)
            if (input.parentElement.classList.contains('password-field')) {
                input.parentElement.insertAdjacentElement('afterend', errorDiv);
            } else {
                input.insertAdjacentElement('afterend', errorDiv);
            }

            // Real-time checking on blur and input
            input.addEventListener('blur', () => validateInput(input, errorDiv));
            input.addEventListener('input', () => validateInput(input, errorDiv));
        });

        // Check on submit
        form.addEventListener('submit', (e) => {
            let isValid = true;
            inputs.forEach(input => {
                const errorDiv = input.parentElement.classList.contains('password-field') ? input.parentElement.nextElementSibling : input.nextElementSibling;
                if (!validateInput(input, errorDiv)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Prevent the actual form submit logic from firing!
                
                // Shake the form slightly to show error
                form.classList.add('shake-anim');
                setTimeout(() => form.classList.remove('shake-anim'), 400);
            }
        }, true); // Use capture phase to intercept before other submit listeners!
    });
    
    function validateInput(input, errorDiv) {
        if (!input.validity.valid) {
            input.style.borderColor = '#de350b';
            input.style.backgroundColor = '#fff8f6';
            errorDiv.style.display = 'block';
            
            // Custom Label name for better error reading
            const labelEl = document.querySelector(`label[for="${input.id}"]`);
            const fieldName = labelEl ? labelEl.textContent.trim() : 'This field';

            if (input.validity.valueMissing) {
                errorDiv.textContent = `${fieldName} is required.`;
            } else if (input.validity.typeMismatch) {
                errorDiv.textContent = `Please enter a valid format for ${fieldName}.`;
            } else if (input.validity.patternMismatch) {
                errorDiv.textContent = `Format is incorrect. Check your input.`;
            } else {
                errorDiv.textContent = `Invalid input.`;
            }
            return false;
        } else {
            input.style.borderColor = '';
            input.style.backgroundColor = '';
            errorDiv.style.display = 'none';
            return true;
        }
    }
});
