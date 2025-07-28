/**
 * TPAK DQ Survey Display JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * TPAK Survey Display Class
     */
    class TPAKSurveyDisplay {
        constructor(container, options = {}) {
            this.container = $(container);
            this.options = $.extend({
                surveyId: null,
                responseData: {},
                autoSave: true,
                saveInterval: 30000, // 30 seconds
                showProgress: true,
                showNavigation: true,
                validateOnChange: true
            }, options);
            
            this.currentQuestion = 0;
            this.questions = [];
            this.answers = {};
            this.errors = {};
            this.saveTimer = null;
            
            this.init();
        }
        
        /**
         * Initialize the survey display
         */
        init() {
            this.loadSurveyStructure();
            this.setupEventListeners();
            this.setupAutoSave();
            this.renderCurrentQuestion();
        }
        
        /**
         * Load survey structure from backend
         */
        async loadSurveyStructure() {
            try {
                const response = await $.ajax({
                    url: tpak_dq.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'tpak_get_survey_structure',
                        survey_id: this.options.surveyId,
                        nonce: tpak_dq.nonce
                    }
                });
                
                if (response.success) {
                    this.questions = response.data.questions || [];
                    this.renderSurvey();
                } else {
                    this.showError('ไม่สามารถโหลดโครงสร้างแบบสอบถามได้: ' + response.data);
                }
            } catch (error) {
                console.error('Error loading survey structure:', error);
                this.showError('เกิดข้อผิดพลาดในการโหลดแบบสอบถาม');
            }
        }
        
        /**
         * Render the entire survey
         */
        renderSurvey() {
            if (this.questions.length === 0) {
                this.container.html('<div class="tpak-error">ไม่พบคำถามในแบบสอบถามนี้</div>');
                return;
            }
            
            this.renderHeader();
            this.renderProgress();
            this.renderQuestions();
            this.renderNavigation();
        }
        
        /**
         * Render survey header
         */
        renderHeader() {
            const header = `
                <div class="tpak-survey-header">
                    <h1 class="tpak-survey-title">แบบสอบถาม TPAK DQ System</h1>
                    <p class="tpak-survey-description">กรุณาตอบคำถามให้ครบถ้วนและถูกต้อง</p>
                </div>
            `;
            this.container.prepend(header);
        }
        
        /**
         * Render progress bar
         */
        renderProgress() {
            if (!this.options.showProgress) return;
            
            const progress = `
                <div class="tpak-progress-container">
                    <div class="tpak-progress-text">
                        คำถามที่ ${this.currentQuestion + 1} จาก ${this.questions.length}
                    </div>
                    <div class="tpak-progress-bar">
                        <div class="tpak-progress-fill" style="width: ${this.getProgressPercentage()}%"></div>
                    </div>
                </div>
            `;
            this.container.find('.tpak-survey-header').after(progress);
        }
        
        /**
         * Render all questions
         */
        renderQuestions() {
            const questionsContainer = $('<div class="tpak-questions-container"></div>');
            
            this.questions.forEach((question, index) => {
                const questionElement = this.renderQuestion(question, index);
                questionsContainer.append(questionElement);
            });
            
            this.container.append(questionsContainer);
        }
        
        /**
         * Render individual question
         */
        renderQuestion(questionData, index) {
            const questionType = questionData.type || 'T';
            const handler = this.getQuestionHandler(questionType);
            
            const questionElement = $(`
                <div class="tpak-question" data-question-index="${index}" data-question-type="${questionType}">
                    ${handler.render(questionData, this.options.responseData)}
                </div>
            `);
            
            // Add event listeners for this question
            this.setupQuestionEventListeners(questionElement, questionData);
            
            return questionElement;
        }
        
        /**
         * Get question handler based on type
         */
        getQuestionHandler(type) {
            const handlers = {
                'L': new TPAKRadioHandler(),
                'M': new TPAKCheckboxHandler(),
                'T': new TPAKTextHandler(),
                'S': new TPAKShortTextHandler(),
                'U': new TPAKLongTextHandler(),
                'N': new TPAKNumericHandler(),
                'Y': new TPAKYesNoHandler()
            };
            
            return handlers[type] || handlers['T'];
        }
        
        /**
         * Setup event listeners for a question
         */
        setupQuestionEventListeners(questionElement, questionData) {
            const questionCode = questionData.code;
            
            // Handle input changes
            questionElement.on('change', 'input, textarea, select', (e) => {
                this.handleAnswerChange(questionCode, e.target);
            });
            
            // Handle character counting for text inputs
            questionElement.on('input', 'input[data-max-length], textarea[data-max-length]', (e) => {
                this.updateCharacterCount(e.target);
            });
            
            // Handle validation on blur
            if (this.options.validateOnChange) {
                questionElement.on('blur', 'input, textarea, select', (e) => {
                    this.validateQuestion(questionCode, e.target);
                });
            }
        }
        
        /**
         * Handle answer change
         */
        handleAnswerChange(questionCode, element) {
            const value = this.getElementValue(element);
            this.answers[questionCode] = value;
            
            // Mark question as completed
            const questionElement = $(element).closest('.tpak-question');
            questionElement.addClass('is-completed');
            
            // Auto save if enabled
            if (this.options.autoSave) {
                this.scheduleAutoSave();
            }
            
            // Trigger custom event
            this.container.trigger('answerChanged', [questionCode, value]);
        }
        
        /**
         * Get element value based on type
         */
        getElementValue(element) {
            const $element = $(element);
            const type = element.type;
            
            if (type === 'checkbox') {
                const name = element.name;
                const checkboxes = $(`input[name="${name}"]:checked`);
                return checkboxes.map(function() {
                    return this.value;
                }).get();
            } else if (type === 'radio') {
                return element.value;
            } else {
                return $element.val();
            }
        }
        
        /**
         * Update character count for text inputs
         */
        updateCharacterCount(element) {
            const $element = $(element);
            const maxLength = parseInt($element.data('max-length'));
            const currentLength = $element.val().length;
            const counter = $element.siblings('.tpak-char-counter');
            
            if (counter.length) {
                const countElement = counter.find('.tpak-char-count');
                countElement.text(currentLength);
                
                // Update counter color based on usage
                counter.removeClass('warning danger');
                if (currentLength > maxLength * 0.9) {
                    counter.addClass('warning');
                }
                if (currentLength > maxLength) {
                    counter.addClass('danger');
                }
            }
        }
        
        /**
         * Validate question
         */
        validateQuestion(questionCode, element) {
            const $element = $(element);
            const value = this.getElementValue(element);
            const questionData = this.getQuestionData(questionCode);
            
            let isValid = true;
            let errorMessage = '';
            
            // Required field validation
            if (questionData.required && (!value || (Array.isArray(value) && value.length === 0))) {
                isValid = false;
                errorMessage = 'กรุณาตอบคำถามนี้';
            }
            
            // Type-specific validation
            if (isValid && value) {
                switch (questionData.type) {
                    case 'N':
                        if (isNaN(value) || value < (questionData.min_value || -Infinity) || value > (questionData.max_value || Infinity)) {
                            isValid = false;
                            errorMessage = 'กรุณาใส่ตัวเลขที่ถูกต้อง';
                        }
                        break;
                    case 'T':
                    case 'S':
                    case 'U':
                        const maxLength = questionData.max_length;
                        if (maxLength && value.length > maxLength) {
                            isValid = false;
                            errorMessage = `ข้อความยาวเกิน ${maxLength} ตัวอักษร`;
                        }
                        break;
                }
            }
            
            // Update error display
            this.updateQuestionError(questionCode, isValid, errorMessage);
            
            return isValid;
        }
        
        /**
         * Update question error display
         */
        updateQuestionError(questionCode, isValid, errorMessage) {
            const questionElement = this.container.find(`[data-question-code="${questionCode}"]`);
            
            // Remove existing error
            questionElement.removeClass('has-error');
            questionElement.find('.tpak-error').remove();
            
            // Add new error if invalid
            if (!isValid && errorMessage) {
                questionElement.addClass('has-error');
                questionElement.find('.tpak-question-content').append(`
                    <div class="tpak-error">${errorMessage}</div>
                `);
            }
        }
        
        /**
         * Get question data by code
         */
        getQuestionData(questionCode) {
            return this.questions.find(q => q.code === questionCode) || {};
        }
        
        /**
         * Render navigation buttons
         */
        renderNavigation() {
            if (!this.options.showNavigation) return;
            
            const navigation = `
                <div class="tpak-navigation">
                    <button type="button" class="tpak-nav-button prev" disabled>
                        ← คำถามก่อนหน้า
                    </button>
                    <button type="button" class="tpak-nav-button next">
                        คำถามถัดไป →
                    </button>
                    <button type="button" class="tpak-nav-button submit" style="display: none;">
                        ส่งคำตอบ
                    </button>
                </div>
            `;
            this.container.append(navigation);
            
            this.setupNavigationEventListeners();
        }
        
        /**
         * Setup navigation event listeners
         */
        setupNavigationEventListeners() {
            const $prev = this.container.find('.tpak-nav-button.prev');
            const $next = this.container.find('.tpak-nav-button.next');
            const $submit = this.container.find('.tpak-nav-button.submit');
            
            $prev.on('click', () => this.previousQuestion());
            $next.on('click', () => this.nextQuestion());
            $submit.on('click', () => this.submitSurvey());
        }
        
        /**
         * Navigate to previous question
         */
        previousQuestion() {
            if (this.currentQuestion > 0) {
                this.currentQuestion--;
                this.updateNavigation();
                this.scrollToQuestion();
            }
        }
        
        /**
         * Navigate to next question
         */
        nextQuestion() {
            if (this.currentQuestion < this.questions.length - 1) {
                this.currentQuestion++;
                this.updateNavigation();
                this.scrollToQuestion();
            }
        }
        
        /**
         * Update navigation buttons
         */
        updateNavigation() {
            const $prev = this.container.find('.tpak-nav-button.prev');
            const $next = this.container.find('.tpak-nav-button.next');
            const $submit = this.container.find('.tpak-nav-button.submit');
            
            $prev.prop('disabled', this.currentQuestion === 0);
            $next.toggle(this.currentQuestion < this.questions.length - 1);
            $submit.toggle(this.currentQuestion === this.questions.length - 1);
            
            // Update progress
            this.updateProgress();
        }
        
        /**
         * Update progress display
         */
        updateProgress() {
            const $progressText = this.container.find('.tpak-progress-text');
            const $progressFill = this.container.find('.tpak-progress-fill');
            
            if ($progressText.length) {
                $progressText.text(`คำถามที่ ${this.currentQuestion + 1} จาก ${this.questions.length}`);
            }
            
            if ($progressFill.length) {
                $progressFill.css('width', this.getProgressPercentage() + '%');
            }
        }
        
        /**
         * Get progress percentage
         */
        getProgressPercentage() {
            return Math.round(((this.currentQuestion + 1) / this.questions.length) * 100);
        }
        
        /**
         * Scroll to current question
         */
        scrollToQuestion() {
            const questionElement = this.container.find(`[data-question-index="${this.currentQuestion}"]`);
            if (questionElement.length) {
                $('html, body').animate({
                    scrollTop: questionElement.offset().top - 100
                }, 500);
            }
        }
        
        /**
         * Setup auto save functionality
         */
        setupAutoSave() {
            if (this.options.autoSave) {
                this.scheduleAutoSave();
            }
        }
        
        /**
         * Schedule auto save
         */
        scheduleAutoSave() {
            if (this.saveTimer) {
                clearTimeout(this.saveTimer);
            }
            
            this.saveTimer = setTimeout(() => {
                this.saveAnswers();
            }, this.options.saveInterval);
        }
        
        /**
         * Save answers to backend
         */
        async saveAnswers() {
            try {
                const response = await $.ajax({
                    url: tpak_dq.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'tpak_save_survey_answers',
                        survey_id: this.options.surveyId,
                        answers: this.answers,
                        nonce: tpak_dq.nonce
                    }
                });
                
                if (response.success) {
                    console.log('Answers saved successfully');
                } else {
                    console.error('Error saving answers:', response.data);
                }
            } catch (error) {
                console.error('Error saving answers:', error);
            }
        }
        
        /**
         * Submit survey
         */
        async submitSurvey() {
            // Validate all questions
            const isValid = this.validateAllQuestions();
            
            if (!isValid) {
                this.showError('กรุณาตอบคำถามให้ครบถ้วนก่อนส่ง');
                return;
            }
            
            try {
                const response = await $.ajax({
                    url: tpak_dq.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'tpak_submit_survey',
                        survey_id: this.options.surveyId,
                        answers: this.answers,
                        nonce: tpak_dq.nonce
                    }
                });
                
                if (response.success) {
                    this.showSuccess('ส่งคำตอบเรียบร้อยแล้ว');
                    this.container.trigger('surveySubmitted', [this.answers]);
                } else {
                    this.showError('เกิดข้อผิดพลาดในการส่งคำตอบ: ' + response.data);
                }
            } catch (error) {
                console.error('Error submitting survey:', error);
                this.showError('เกิดข้อผิดพลาดในการส่งคำตอบ');
            }
        }
        
        /**
         * Validate all questions
         */
        validateAllQuestions() {
            let isValid = true;
            
            this.questions.forEach(question => {
                const questionElement = this.container.find(`[data-question-code="${question.code}"]`);
                const inputs = questionElement.find('input, textarea, select');
                
                inputs.each((index, element) => {
                    if (!this.validateQuestion(question.code, element)) {
                        isValid = false;
                    }
                });
            });
            
            return isValid;
        }
        
        /**
         * Setup global event listeners
         */
        setupEventListeners() {
            // Handle form submission
            this.container.on('submit', 'form', (e) => {
                e.preventDefault();
                this.submitSurvey();
            });
            
            // Handle keyboard navigation
            $(document).on('keydown', (e) => {
                if (e.key === 'ArrowLeft' && !e.ctrlKey && !e.altKey) {
                    e.preventDefault();
                    this.previousQuestion();
                } else if (e.key === 'ArrowRight' && !e.ctrlKey && !e.altKey) {
                    e.preventDefault();
                    this.nextQuestion();
                }
            });
        }
        
        /**
         * Show error message
         */
        showError(message) {
            const errorDiv = $(`<div class="tpak-error-message">${message}</div>`);
            this.container.prepend(errorDiv);
            
            setTimeout(() => {
                errorDiv.fadeOut(() => errorDiv.remove());
            }, 5000);
        }
        
        /**
         * Show success message
         */
        showSuccess(message) {
            const successDiv = $(`<div class="tpak-success-message">${message}</div>`);
            this.container.prepend(successDiv);
            
            setTimeout(() => {
                successDiv.fadeOut(() => successDiv.remove());
            }, 3000);
        }
        
        /**
         * Get current answers
         */
        getAnswers() {
            return this.answers;
        }
        
        /**
         * Set answers
         */
        setAnswers(answers) {
            this.answers = answers;
            this.renderAnswers();
        }
        
        /**
         * Render existing answers
         */
        renderAnswers() {
            Object.keys(this.answers).forEach(questionCode => {
                const questionElement = this.container.find(`[data-question-code="${questionCode}"]`);
                const value = this.answers[questionCode];
                
                if (questionElement.length) {
                    this.setElementValue(questionElement, questionCode, value);
                    questionElement.addClass('is-completed');
                }
            });
        }
        
        /**
         * Set element value
         */
        setElementValue(questionElement, questionCode, value) {
            const questionData = this.getQuestionData(questionCode);
            
            switch (questionData.type) {
                case 'L':
                case 'Y':
                    questionElement.find(`input[value="${value}"]`).prop('checked', true);
                    break;
                case 'M':
                    if (Array.isArray(value)) {
                        value.forEach(v => {
                            questionElement.find(`input[value="${v}"]`).prop('checked', true);
                        });
                    }
                    break;
                case 'T':
                case 'S':
                case 'U':
                case 'N':
                    questionElement.find('input, textarea').val(value);
                    this.updateCharacterCount(questionElement.find('input, textarea')[0]);
                    break;
            }
        }
    }

    /**
     * Question Handler Classes
     */
    class TPAKQuestionHandler {
        render(questionData, responseData) {
            return this.renderQuestion(questionData, responseData);
        }
        
        renderQuestion(questionData, responseData) {
            // Base implementation - should be overridden
            return '<div class="tpak-error">Question type not implemented</div>';
        }
    }

    class TPAKRadioHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const options = questionData.options || [];
            const value = responseData[questionData.code] || '';
            
            let html = '<div class="tpak-radio-group">';
            options.forEach(option => {
                const checked = value === option.value ? 'checked' : '';
                html += `
                    <div class="tpak-radio-item">
                        <input type="radio" 
                               id="tpak_${questionData.code}_${option.value}"
                               name="${questionData.code}"
                               value="${option.value}"
                               ${checked}
                               class="tpak-radio-input">
                        <label for="tpak_${questionData.code}_${option.value}" class="tpak-radio-label">
                            ${option.label}
                        </label>
                    </div>
                `;
            });
            html += '</div>';
            
            return html;
        }
    }

    class TPAKCheckboxHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const options = questionData.options || [];
            const values = responseData[questionData.code] || [];
            
            let html = '<div class="tpak-checkbox-group">';
            options.forEach(option => {
                const checked = Array.isArray(values) && values.includes(option.value) ? 'checked' : '';
                html += `
                    <div class="tpak-checkbox-item">
                        <input type="checkbox" 
                               id="tpak_${questionData.code}_${option.value}"
                               name="${questionData.code}[]"
                               value="${option.value}"
                               ${checked}
                               class="tpak-checkbox-input">
                        <label for="tpak_${questionData.code}_${option.value}" class="tpak-checkbox-label">
                            ${option.label}
                        </label>
                    </div>
                `;
            });
            html += '</div>';
            
            return html;
        }
    }

    class TPAKTextHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            const maxLength = questionData.max_length || '';
            const placeholder = questionData.placeholder || '';
            
            let html = '<div class="tpak-text-input-group">';
            html += `<input type="text" 
                           id="tpak_${questionData.code}"
                           name="${questionData.code}"
                           value="${value}"
                           ${maxLength ? `maxlength="${maxLength}" data-max-length="${maxLength}"` : ''}
                           ${placeholder ? `placeholder="${placeholder}"` : ''}
                           class="tpak-text-input">`;
            
            if (maxLength) {
                html += `<div class="tpak-char-counter">
                            <span class="tpak-char-count">${value.length}</span> / ${maxLength}
                         </div>`;
            }
            html += '</div>';
            
            return html;
        }
    }

    class TPAKShortTextHandler extends TPAKTextHandler {
        renderQuestion(questionData, responseData) {
            questionData.placeholder = questionData.placeholder || 'กรุณาใส่ข้อความสั้นๆ';
            questionData.max_length = questionData.max_length || 255;
            return super.renderQuestion(questionData, responseData);
        }
    }

    class TPAKLongTextHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            const maxLength = questionData.max_length || 5000;
            const placeholder = questionData.placeholder || 'กรุณาใส่ข้อความโดยละเอียด';
            const rows = questionData.rows || 5;
            
            let html = '<div class="tpak-textarea-group">';
            html += `<textarea id="tpak_${questionData.code}"
                              name="${questionData.code}"
                              rows="${rows}"
                              maxlength="${maxLength}"
                              data-max-length="${maxLength}"
                              placeholder="${placeholder}"
                              class="tpak-textarea tpak-long-text">${value}</textarea>`;
            
            html += `<div class="tpak-char-counter">
                        <span class="tpak-char-count">${value.length}</span> / ${maxLength}
                     </div>`;
            html += '</div>';
            
            return html;
        }
    }

    class TPAKNumericHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            const minValue = questionData.min_value || '';
            const maxValue = questionData.max_value || '';
            const placeholder = questionData.placeholder || 'กรุณาใส่ตัวเลข';
            
            let html = '<div class="tpak-numeric-input-group">';
            html += `<input type="number" 
                           id="tpak_${questionData.code}"
                           name="${questionData.code}"
                           value="${value}"
                           ${minValue !== '' ? `min="${minValue}"` : ''}
                           ${maxValue !== '' ? `max="${maxValue}"` : ''}
                           placeholder="${placeholder}"
                           class="tpak-numeric-input">`;
            
            if (minValue !== '' || maxValue !== '') {
                html += '<div class="tpak-numeric-range">';
                if (minValue !== '') {
                    html += `<span class="tpak-min-value">ค่าต่ำสุด: ${minValue}</span>`;
                }
                if (maxValue !== '') {
                    html += `<span class="tpak-max-value">ค่าสูงสุด: ${maxValue}</span>`;
                }
                html += '</div>';
            }
            html += '</div>';
            
            return html;
        }
    }

    class TPAKYesNoHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            
            let html = '<div class="tpak-yesno-group">';
            html += `
                <div class="tpak-radio-item">
                    <input type="radio" 
                           id="tpak_${questionData.code}_Y"
                           name="${questionData.code}"
                           value="Y"
                           ${value === 'Y' ? 'checked' : ''}
                           class="tpak-radio-input">
                    <label for="tpak_${questionData.code}_Y" class="tpak-radio-label">
                        ใช่
                    </label>
                </div>
                <div class="tpak-radio-item">
                    <input type="radio" 
                           id="tpak_${questionData.code}_N"
                           name="${questionData.code}"
                           value="N"
                           ${value === 'N' ? 'checked' : ''}
                           class="tpak-radio-input">
                    <label for="tpak_${questionData.code}_N" class="tpak-radio-label">
                        ไม่ใช่
                    </label>
                </div>
            `;
            html += '</div>';
            
            return html;
        }
    }

    /**
     * Initialize survey display when document is ready
     */
    $(document).ready(function() {
        // Auto-initialize if container exists
        $('.tpak-survey-container').each(function() {
            const $container = $(this);
            const surveyId = $container.data('survey-id');
            const responseData = $container.data('response-data') || {};
            
            if (surveyId) {
                new TPAKSurveyDisplay($container, {
                    surveyId: surveyId,
                    responseData: responseData
                });
            }
        });
    });

    /**
     * Make TPAKSurveyDisplay available globally
     */
    window.TPAKSurveyDisplay = TPAKSurveyDisplay;

})(jQuery); 