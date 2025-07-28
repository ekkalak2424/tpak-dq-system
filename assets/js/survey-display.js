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
            // Simple Questions
            'L': new TPAKRadioHandler(),
            'M': new TPAKCheckboxHandler(),
            'T': new TPAKTextHandler(),
            'S': new TPAKShortTextHandler(),
            'U': new TPAKLongTextHandler(),
            'N': new TPAKNumericHandler(),
            'Y': new TPAKYesNoHandler(),
            
            // Complex Questions
            'A': new TPAKArrayHandler(),
            'B': new TPAKArrayTextHandler(),
            'C': new TPAKArrayYesNoHandler(),
            'R': new TPAKRankingHandler(),
            'W': new TPAKDateTimeHandler(),
            'Z': new TPAKFileUploadHandler(),
            
            // Advanced Questions
            'J': new TPAKMatrixHandler(),
            'K': new TPAKMatrixTextHandler(),
            'P': new TPAKMatrixNumericHandler(),
            'V': new TPAKSliderHandler(),
            '!': new TPAKDropdownHandler(),
            'O': new TPAKListCommentHandler()
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
        
        // Handle complex question specific events
        this.setupComplexQuestionEvents(questionElement, questionData);
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
     * Setup complex question specific events
     */
    setupComplexQuestionEvents(questionElement, questionData) {
        const questionType = questionData.type;
        
        switch (questionType) {
            case 'R': // Ranking
                this.setupRankingEvents(questionElement);
                break;
            case 'Z': // File Upload
                this.setupFileUploadEvents(questionElement);
                break;
            case 'W': // Date/Time
                this.setupDateTimeEvents(questionElement);
                break;
            case 'V': // Slider
                this.setupSliderEvents(questionElement);
                break;
        }
        
        // Setup logic events
        this.setupLogicEvents(questionElement, questionData);
    }
    
    /**
     * Setup ranking question events
     */
    setupRankingEvents(questionElement) {
        const rankingList = questionElement.find('.tpak-ranking-list');
        
        // Handle drag and drop
        rankingList.sortable({
            handle: '.tpak-ranking-handle',
            axis: 'y',
            opacity: 0.6,
            update: (event, ui) => {
                this.updateRankingOrder(questionElement);
            }
        });
        
        // Handle up/down buttons
        questionElement.on('click', '.tpak-ranking-up', (e) => {
            e.preventDefault();
            this.moveRankingItem(questionElement, 'up');
        });
        
        questionElement.on('click', '.tpak-ranking-down', (e) => {
            e.preventDefault();
            this.moveRankingItem(questionElement, 'down');
        });
    }
    
    /**
     * Update ranking order
     */
    updateRankingOrder(questionElement) {
        const items = questionElement.find('.tpak-ranking-item');
        const questionCode = questionElement.find('.tpak-ranking-list').data('question-code');
        
        items.each((index, item) => {
            const $item = $(item);
            const value = $item.data('value');
            const rank = index + 1;
            
            $item.data('rank', rank);
            $item.find('.tpak-ranking-number').text(rank);
            $item.find('.tpak-ranking-input').val(rank);
            
            // Update button states
            const $up = $item.find('.tpak-ranking-up');
            const $down = $item.find('.tpak-ranking-down');
            
            $up.prop('disabled', index === 0);
            $down.prop('disabled', index === items.length - 1);
        });
        
        // Trigger answer change
        this.handleAnswerChange(questionCode, questionElement.find('.tpak-ranking-input')[0]);
    }
    
    /**
     * Move ranking item up or down
     */
    moveRankingItem(questionElement, direction) {
        const items = questionElement.find('.tpak-ranking-item');
        const activeItem = questionElement.find('.tpak-ranking-item:focus');
        
        if (activeItem.length === 0) return;
        
        const currentIndex = activeItem.index();
        let newIndex;
        
        if (direction === 'up' && currentIndex > 0) {
            newIndex = currentIndex - 1;
        } else if (direction === 'down' && currentIndex < items.length - 1) {
            newIndex = currentIndex + 1;
        } else {
            return;
        }
        
        const targetItem = items.eq(newIndex);
        
        if (direction === 'up') {
            activeItem.insertBefore(targetItem);
        } else {
            activeItem.insertAfter(targetItem);
        }
        
        this.updateRankingOrder(questionElement);
    }
    
    /**
     * Setup file upload events
     */
    setupFileUploadEvents(questionElement) {
        const uploadArea = questionElement.find('.tpak-file-upload-area');
        const fileInput = questionElement.find('.tpak-file-input');
        const progressBar = questionElement.find('.tpak-file-upload-progress');
        
        // Handle click to select file
        uploadArea.on('click', () => {
            fileInput.click();
        });
        
        // Handle file selection
        fileInput.on('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleFileSelection(questionElement, file);
            }
        });
        
        // Handle drag and drop
        uploadArea.on('dragover', (e) => {
            e.preventDefault();
            uploadArea.addClass('dragover');
        });
        
        uploadArea.on('dragleave', () => {
            uploadArea.removeClass('dragover');
        });
        
        uploadArea.on('drop', (e) => {
            e.preventDefault();
            uploadArea.removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileSelection(questionElement, files[0]);
            }
        });
        
        // Handle file removal
        questionElement.on('click', '.tpak-file-remove', (e) => {
            e.preventDefault();
            this.removeFile(questionElement);
        });
    }
    
    /**
     * Handle file selection
     */
    handleFileSelection(questionElement, file) {
        const uploadArea = questionElement.find('.tpak-file-upload-area');
        const maxSize = parseInt(uploadArea.data('max-size'));
        const allowedTypes = uploadArea.data('allowed-types').split(',');
        
        // Validate file size
        if (file.size > maxSize) {
            alert(`ไฟล์มีขนาดใหญ่เกินไป (สูงสุด ${this.formatFileSize(maxSize)})`);
            return;
        }
        
        // Validate file type
        const fileExtension = file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(fileExtension)) {
            alert(`ไม่รองรับไฟล์ประเภทนี้ (รองรับ: ${allowedTypes.join(', ')})`);
            return;
        }
        
        // Show progress
        const progressBar = questionElement.find('.tpak-file-upload-progress');
        progressBar.show();
        
        // Simulate upload progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 20;
            if (progress >= 100) {
                progress = 100;
                clearInterval(progressInterval);
                
                // Update UI
                this.updateFilePreview(questionElement, file);
                progressBar.hide();
            }
            
            progressBar.find('.tpak-progress-fill').css('width', progress + '%');
            progressBar.find('.tpak-progress-text').text(Math.round(progress) + '%');
        }, 100);
    }
    
    /**
     * Update file preview
     */
    updateFilePreview(questionElement, file) {
        const preview = questionElement.find('.tpak-file-upload-preview');
        const fileInput = questionElement.find('.tpak-file-input');
        const currentInput = questionElement.find('.tpak-file-current');
        
        preview.html(`
            <div class="tpak-file-preview">
                <span class="dashicons dashicons-paperclip"></span>
                <span class="tpak-file-name">${file.name}</span>
                <button type="button" class="tpak-file-remove">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        `);
        
        // Update hidden inputs
        currentInput.val(file.name);
        
        // Trigger answer change
        const questionCode = questionElement.closest('.tpak-question').data('question-code');
        this.handleAnswerChange(questionCode, currentInput[0]);
    }
    
    /**
     * Remove file
     */
    removeFile(questionElement) {
        const preview = questionElement.find('.tpak-file-upload-preview');
        const fileInput = questionElement.find('.tpak-file-input');
        const currentInput = questionElement.find('.tpak-file-current');
        
        preview.html(`
            <div class="tpak-file-upload-placeholder">
                <span class="dashicons dashicons-upload"></span>
                <p>คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</p>
                <p class="tpak-file-upload-info">
                    รองรับไฟล์: jpg, jpeg, png, pdf, doc, docx<br>
                    ขนาดสูงสุด: 5 MB
                </p>
            </div>
        `);
        
        // Clear inputs
        fileInput.val('');
        currentInput.val('');
        
        // Trigger answer change
        const questionCode = questionElement.closest('.tpak-question').data('question-code');
        this.handleAnswerChange(questionCode, currentInput[0]);
    }
    
    /**
     * Setup date/time events
     */
    setupDateTimeEvents(questionElement) {
        const dateInput = questionElement.find('.tpak-datetime-input');
        const timeInput = questionElement.find('.tpak-datetime-time-input');
        const calendarToggle = questionElement.find('.tpak-calendar-toggle');
        
        // Handle date/time change
        dateInput.on('change', () => {
            this.updateDateTimeValue(questionElement);
        });
        
        if (timeInput.length) {
            timeInput.on('change', () => {
                this.updateDateTimeValue(questionElement);
            });
        }
        
        // Handle calendar toggle
        calendarToggle.on('click', (e) => {
            e.preventDefault();
            dateInput.focus();
        });
    }
    
    /**
     * Update date/time value
     */
    updateDateTimeValue(questionElement) {
        const dateInput = questionElement.find('.tpak-datetime-input');
        const timeInput = questionElement.find('.tpak-datetime-time-input');
        const questionCode = questionElement.closest('.tpak-question').data('question-code');
        
        const dateValue = dateInput.val();
        const timeValue = timeInput.length ? timeInput.val() : '';
        
        let combinedValue = '';
        if (dateValue) {
            combinedValue = dateValue;
            if (timeValue) {
                combinedValue += ' ' + timeValue;
            }
        }
        
        // Update hidden input or trigger change
        const hiddenInput = questionElement.find('input[type="hidden"]');
        if (hiddenInput.length) {
            hiddenInput.val(combinedValue);
            this.handleAnswerChange(questionCode, hiddenInput[0]);
        } else {
            // Create temporary input for change event
            const tempInput = $('<input>').attr({
                type: 'hidden',
                name: questionCode,
                value: combinedValue
            });
            this.handleAnswerChange(questionCode, tempInput[0]);
        }
    }
    
    /**
     * Setup slider events
     */
    setupSliderEvents(questionElement) {
        const sliderInput = questionElement.find('.tpak-slider-input');
        const currentValue = questionElement.find('.tpak-slider-current');
        const questionCode = questionElement.closest('.tpak-question').data('question-code');
        
        sliderInput.on('input', (e) => {
            const value = e.target.value;
            currentValue.text(value);
            this.handleAnswerChange(questionCode, e.target);
        });
        
        sliderInput.on('change', (e) => {
            this.handleAnswerChange(questionCode, e.target);
        });
    }
    
    /**
     * Setup logic events
     */
    setupLogicEvents(questionElement, questionData) {
        const questionCode = questionData.code;
        const logic = questionData.logic || {};
        
        // Handle conditional logic
        if (logic.show_if || logic.hide_if) {
            this.setupConditionalLogic(questionElement, questionData);
        }
        
        // Handle skip logic
        if (logic.skip_to) {
            this.setupSkipLogic(questionElement, questionData);
        }
    }
    
    /**
     * Setup conditional logic
     */
    setupConditionalLogic(questionElement, questionData) {
        const questionCode = questionData.code;
        const logic = questionData.logic || {};
        
        // Monitor changes in dependent questions
        const dependencies = this.getQuestionDependencies(questionData);
        
        dependencies.forEach(dependency => {
            $(document).on('change', `[name="${dependency}"], [name^="${dependency}["]`, () => {
                this.evaluateConditionalLogic(questionElement, questionData);
            });
        });
    }
    
    /**
     * Setup skip logic
     */
    setupSkipLogic(questionElement, questionData) {
        const questionCode = questionData.code;
        const logic = questionData.logic || {};
        
        if (logic.skip_to) {
            questionElement.on('change', 'input, select, textarea', (e) => {
                this.evaluateSkipLogic(questionElement, questionData);
            });
        }
    }
    
    /**
     * Get question dependencies
     */
    getQuestionDependencies(questionData) {
        const dependencies = [];
        const logic = questionData.logic || {};
        
        // Get dependencies from show_if conditions
        if (logic.show_if) {
            logic.show_if.forEach(condition => {
                if (condition.question) {
                    dependencies.push(condition.question);
                }
            });
        }
        
        // Get dependencies from hide_if conditions
        if (logic.hide_if) {
            logic.hide_if.forEach(condition => {
                if (condition.question) {
                    dependencies.push(condition.question);
                }
            });
        }
        
        return [...new Set(dependencies)];
    }
    
    /**
     * Evaluate conditional logic
     */
    evaluateConditionalLogic(questionElement, questionData) {
        const logic = questionData.logic || {};
        let shouldShow = true;
        
        // Evaluate show_if conditions
        if (logic.show_if) {
            shouldShow = this.evaluateConditions(logic.show_if);
        }
        
        // Evaluate hide_if conditions
        if (logic.hide_if && shouldShow) {
            shouldShow = !this.evaluateConditions(logic.hide_if);
        }
        
        // Show/hide question based on evaluation
        if (shouldShow) {
            questionElement.show();
            questionElement.removeClass('tpak-question-hidden');
        } else {
            questionElement.hide();
            questionElement.addClass('tpak-question-hidden');
        }
        
        // Update progress
        this.updateProgress();
    }
    
    /**
     * Evaluate conditions
     */
    evaluateConditions(conditions) {
        if (!conditions || conditions.length === 0) {
            return true;
        }
        
        for (const condition of conditions) {
            const result = this.evaluateSingleCondition(condition);
            
            if (condition.operator === 'AND' && !result) {
                return false;
            }
            
            if (condition.operator === 'OR' && result) {
                return true;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate single condition
     */
    evaluateSingleCondition(condition) {
        const questionCode = condition.question;
        const operator = condition.operator;
        const expectedValue = condition.value;
        
        const actualValue = this.getResponseValue(questionCode);
        
        switch (operator) {
            case 'equals':
                return actualValue === expectedValue;
                
            case 'not_equals':
                return actualValue !== expectedValue;
                
            case 'contains':
                return actualValue.includes(expectedValue);
                
            case 'not_contains':
                return !actualValue.includes(expectedValue);
                
            case 'greater_than':
                return parseFloat(actualValue) > parseFloat(expectedValue);
                
            case 'less_than':
                return parseFloat(actualValue) < parseFloat(expectedValue);
                
            case 'greater_than_or_equal':
                return parseFloat(actualValue) >= parseFloat(expectedValue);
                
            case 'less_than_or_equal':
                return parseFloat(actualValue) <= parseFloat(expectedValue);
                
            case 'is_empty':
                return !actualValue || actualValue.trim() === '';
                
            case 'is_not_empty':
                return actualValue && actualValue.trim() !== '';
                
            default:
                return false;
        }
    }
    
    /**
     * Get response value for question
     */
    getResponseValue(questionCode) {
        // Handle array responses
        if (questionCode.includes('[')) {
            const parts = questionCode.split('[');
            const mainQuestion = parts[0];
            const subQuestion = parts[1].replace(']', '');
            
            return this.responseData[mainQuestion]?.[subQuestion] || '';
        }
        
        return this.responseData[questionCode] || '';
    }
    
    /**
     * Evaluate skip logic
     */
    evaluateSkipLogic(questionElement, questionData) {
        const logic = questionData.logic || {};
        
        if (!logic.skip_to) {
            return;
        }
        
        for (const skipRule of logic.skip_to) {
            if (this.evaluateConditions(skipRule.conditions)) {
                this.skipToQuestion(skipRule.target);
                return;
            }
        }
    }
    
    /**
     * Skip to specific question
     */
    skipToQuestion(targetQuestionCode) {
        const targetElement = this.container.find(`[data-question-code="${targetQuestionCode}"]`);
        
        if (targetElement.length > 0) {
            // Scroll to target question
            $('html, body').animate({
                scrollTop: targetElement.offset().top - 100
            }, 500);
            
            // Highlight target question
            targetElement.addClass('tpak-question-highlight');
            setTimeout(() => {
                targetElement.removeClass('tpak-question-highlight');
            }, 2000);
        }
    }
    
    /**
     * Performance monitoring
     */
    startPerformanceMonitoring() {
        this.performanceStartTime = performance.now();
        this.performanceStartMemory = performance.memory ? performance.memory.usedJSHeapSize : 0;
    }
    
    endPerformanceMonitoring() {
        const endTime = performance.now();
        const endMemory = performance.memory ? performance.memory.usedJSHeapSize : 0;
        
        const executionTime = endTime - this.performanceStartTime;
        const memoryUsage = endMemory - this.performanceStartMemory;
        
        // Log performance data
        console.log('Performance Metrics:', {
            executionTime: executionTime.toFixed(2) + 'ms',
            memoryUsage: this.formatBytes(memoryUsage),
            totalMemory: performance.memory ? this.formatBytes(performance.memory.usedJSHeapSize) : 'N/A'
        });
        
        // Send to server if needed
        this.sendPerformanceData({
            executionTime,
            memoryUsage,
            totalMemory: performance.memory ? performance.memory.usedJSHeapSize : 0
        });
    }
    
    /**
     * Send performance data to server
     */
    sendPerformanceData(data) {
        $.ajax({
            url: tpak_dq.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_log_performance',
                nonce: tpak_dq.nonce,
                data: data
            },
            success: function(response) {
                console.log('Performance data logged');
            },
            error: function(xhr, status, error) {
                console.error('Failed to log performance data:', error);
            }
        });
    }
    
    /**
     * Lazy loading for questions
     */
    loadQuestionsLazily(questionIds) {
        const batchSize = 5;
        const batches = this.chunkArray(questionIds, batchSize);
        
        let currentBatch = 0;
        
        const loadNextBatch = () => {
            if (currentBatch >= batches.length) {
                return;
            }
            
            const batch = batches[currentBatch];
            
            $.ajax({
                url: tpak_dq.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_load_questions_batch',
                    nonce: tpak_dq.nonce,
                    question_ids: batch
                },
                success: (response) => {
                    if (response.success) {
                        this.renderQuestionsBatch(response.data.questions);
                        currentBatch++;
                        
                        // Load next batch after a short delay
                        setTimeout(loadNextBatch, 100);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to load questions batch:', error);
                }
            });
        };
        
        loadNextBatch();
    }
    
    /**
     * Render questions batch
     */
    renderQuestionsBatch(questions) {
        questions.forEach(question => {
            const questionElement = this.renderQuestion(question);
            this.container.append(questionElement);
        });
        
        // Update progress
        this.updateProgress();
    }
    
    /**
     * Chunk array into smaller arrays
     */
    chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }
    
    /**
     * Optimize rendering performance
     */
    optimizeRendering() {
        // Use DocumentFragment for better performance
        const fragment = document.createDocumentFragment();
        
        // Batch DOM updates
        this.container.find('.tpak-question').each((index, element) => {
            fragment.appendChild(element);
        });
        
        // Single DOM update
        this.container.empty().append(fragment);
    }
    
    /**
     * Debounce function calls
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Throttle function calls
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    /**
     * Optimize event listeners
     */
    optimizeEventListeners() {
        // Use event delegation for better performance
        this.container.on('change', 'input, select, textarea', this.debounce((e) => {
            this.handleAnswerChange($(e.target).closest('.tpak-question').data('question-code'), e.target);
        }, 300));
        
        // Throttle scroll events
        $(window).on('scroll', this.throttle(() => {
            this.updateProgress();
        }, 100));
    }
    
    /**
     * Memory management
     */
    cleanupMemory() {
        // Remove event listeners
        this.container.off();
        
        // Clear references
        this.questions = null;
        this.responseData = null;
        
        // Force garbage collection if available
        if (window.gc) {
            window.gc();
        }
    }
    
    /**
     * Format bytes for display
     */
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        bytes = Math.max(bytes, 0);
        const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
        const powMin = Math.min(pow, units.length - 1);
        
        bytes /= Math.pow(1024, powMin);
        
        return Math.round(bytes * 100) / 100 + ' ' + units[powMin];
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

    class TPAKArrayHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const subquestions = questionData.subquestions || [];
            const options = questionData.options || [];
            
            if (subquestions.length === 0 || options.length === 0) {
                return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Array Question</div>';
            }
            
            let html = '<div class="tpak-array-container">';
            html += '<table class="tpak-array-table">';
            
            // Header
            html += '<thead><tr>';
            html += '<th class="tpak-array-subquestion-header">คำถาม</th>';
            options.forEach(option => {
                html += `<th class="tpak-array-option-header">${option.label}</th>`;
            });
            html += '</tr></thead>';
            
            // Body
            html += '<tbody>';
            subquestions.forEach(subquestion => {
                html += '<tr class="tpak-array-row">';
                html += `<td class="tpak-array-subquestion">${subquestion.label}</td>`;
                
                options.forEach(option => {
                    const arrayKey = `${questionData.code}[${subquestion.code}]`;
                    const value = responseData[arrayKey] || '';
                    const checked = value === option.value ? 'checked' : '';
                    
                    html += '<td class="tpak-array-cell">';
                    html += `
                        <input type="radio" 
                               id="tpak_${questionData.code}_${subquestion.code}_${option.value}"
                               name="${questionData.code}[${subquestion.code}]"
                               value="${option.value}"
                               ${checked}
                               class="tpak-array-radio">
                        <label for="tpak_${questionData.code}_${subquestion.code}_${option.value}" class="tpak-array-label">
                            ${option.label}
                        </label>
                    `;
                    html += '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            
            return html;
        }
    }

    class TPAKArrayTextHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const subquestions = questionData.subquestions || [];
            
            if (subquestions.length === 0) {
                return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Array Text Question</div>';
            }
            
            let html = '<div class="tpak-array-text-container">';
            html += '<table class="tpak-array-text-table">';
            html += '<thead><tr>';
            html += '<th class="tpak-array-text-subquestion-header">คำถาม</th>';
            html += '<th class="tpak-array-text-input-header">คำตอบ</th>';
            html += '</tr></thead>';
            
            html += '<tbody>';
            subquestions.forEach(subquestion => {
                const arrayKey = `${questionData.code}[${subquestion.code}]`;
                const value = responseData[arrayKey] || '';
                
                html += '<tr class="tpak-array-text-row">';
                html += `<td class="tpak-array-text-subquestion">${subquestion.label}</td>`;
                html += '<td class="tpak-array-text-input-cell">';
                html += `
                    <input type="text" 
                           id="tpak_${questionData.code}_${subquestion.code}"
                           name="${questionData.code}[${subquestion.code}]"
                           value="${value}"
                           class="tpak-array-text-input"
                           placeholder="กรุณาใส่คำตอบ">
                `;
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
            
            return html;
        }
    }

    class TPAKArrayYesNoHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const subquestions = questionData.subquestions || [];
            
            if (subquestions.length === 0) {
                return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Array Yes/No Question</div>';
            }
            
            let html = '<div class="tpak-array-yesno-container">';
            html += '<table class="tpak-array-yesno-table">';
            html += '<thead><tr>';
            html += '<th class="tpak-array-yesno-subquestion-header">คำถาม</th>';
            html += '<th class="tpak-array-yesno-option-header">ใช่</th>';
            html += '<th class="tpak-array-yesno-option-header">ไม่ใช่</th>';
            html += '</tr></thead>';
            
            html += '<tbody>';
            subquestions.forEach(subquestion => {
                const arrayKey = `${questionData.code}[${subquestion.code}]`;
                const value = responseData[arrayKey] || '';
                
                html += '<tr class="tpak-array-yesno-row">';
                html += `<td class="tpak-array-yesno-subquestion">${subquestion.label}</td>`;
                
                // Yes option
                html += '<td class="tpak-array-yesno-cell">';
                html += `
                    <input type="radio" 
                           id="tpak_${questionData.code}_${subquestion.code}_Y"
                           name="${questionData.code}[${subquestion.code}]"
                           value="Y"
                           ${value === 'Y' ? 'checked' : ''}
                           class="tpak-array-yesno-radio">
                    <label for="tpak_${questionData.code}_${subquestion.code}_Y" class="tpak-array-yesno-label">
                        ใช่
                    </label>
                `;
                html += '</td>';
                
                // No option
                html += '<td class="tpak-array-yesno-cell">';
                html += `
                    <input type="radio" 
                           id="tpak_${questionData.code}_${subquestion.code}_N"
                           name="${questionData.code}[${subquestion.code}]"
                           value="N"
                           ${value === 'N' ? 'checked' : ''}
                           class="tpak-array-yesno-radio">
                    <label for="tpak_${questionData.code}_${subquestion.code}_N" class="tpak-array-yesno-label">
                        ไม่ใช่
                    </label>
                `;
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
            
            return html;
        }
    }

    class TPAKRankingHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const options = questionData.options || [];
            
            if (options.length === 0) {
                return '<div class="tpak-error">ไม่พบตัวเลือกสำหรับ Ranking Question</div>';
            }
            
            const rankedOptions = this.getRankedOptions(options, responseData[questionData.code]);
            
            let html = '<div class="tpak-ranking-container">';
            html += '<p class="tpak-ranking-instruction">กรุณาจัดอันดับโดยลากและวาง หรือใช้ปุ่มขึ้น/ลง</p>';
            html += `<div class="tpak-ranking-list" data-question-code="${questionData.code}">`;
            
            rankedOptions.forEach((option, index) => {
                html += `
                    <div class="tpak-ranking-item" data-value="${option.value}" data-rank="${index + 1}">
                        <div class="tpak-ranking-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="tpak-ranking-content">
                            <span class="tpak-ranking-number">${index + 1}</span>
                            <span class="tpak-ranking-label">${option.label}</span>
                        </div>
                        <div class="tpak-ranking-controls">
                            <button type="button" class="tpak-ranking-up" ${index === 0 ? 'disabled' : ''}>
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </button>
                            <button type="button" class="tpak-ranking-down" ${index === rankedOptions.length - 1 ? 'disabled' : ''}>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>
                        <input type="hidden" 
                               name="${questionData.code}[${option.value}]"
                               value="${index + 1}"
                               class="tpak-ranking-input">
                    </div>
                `;
            });
            
            html += '</div></div>';
            return html;
        }
        
        getRankedOptions(options, answerValue) {
            if (!answerValue || !Array.isArray(answerValue)) {
                return options;
            }
            
            // Sort options based on ranking
            return options.sort((a, b) => {
                const rankA = answerValue[a.value] ? parseInt(answerValue[a.value]) : 999;
                const rankB = answerValue[b.value] ? parseInt(answerValue[b.value]) : 999;
                return rankA - rankB;
            });
        }
    }

    class TPAKDateTimeHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            const dateOnly = questionData.date_only || false;
            
            const dateValue = this.formatDateForInput(value);
            const timeValue = this.formatTimeForInput(value);
            
            let html = '<div class="tpak-datetime-container">';
            html += '<div class="tpak-datetime-input-group">';
            html += `
                <input type="date" 
                       id="tpak_${questionData.code}"
                       name="${questionData.code}"
                       value="${dateValue}"
                       class="tpak-datetime-input"
                       ${dateOnly ? '' : 'data-time-enabled="true"'}>
            `;
            
            if (!dateOnly) {
                html += `
                    <input type="time" 
                           id="tpak_${questionData.code}_time"
                           name="${questionData.code}_time"
                           value="${timeValue}"
                           class="tpak-datetime-time-input">
                `;
            }
            
            html += '</div>';
            html += `
                <div class="tpak-datetime-calendar">
                    <button type="button" class="tpak-calendar-toggle">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        เลือกวันที่
                    </button>
                </div>
            `;
            html += '</div>';
            
            return html;
        }
        
        formatDateForInput(dateString) {
            if (!dateString) return '';
            
            const timestamp = new Date(dateString).getTime();
            if (isNaN(timestamp)) return '';
            
            return new Date(timestamp).toISOString().split('T')[0];
        }
        
        formatTimeForInput(dateString) {
            if (!dateString) return '';
            
            const timestamp = new Date(dateString).getTime();
            if (isNaN(timestamp)) return '';
            
            return new Date(timestamp).toTimeString().split(' ')[0];
        }
    }

    class TPAKFileUploadHandler extends TPAKQuestionHandler {
        renderQuestion(questionData, responseData) {
            const value = responseData[questionData.code] || '';
            const maxSize = questionData.max_size || 5242880; // 5MB default
            const allowedTypes = questionData.allowed_types || ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
            
            let html = '<div class="tpak-file-upload-container">';
            html += `<div class="tpak-file-upload-area" data-max-size="${maxSize}" data-allowed-types="${allowedTypes.join(',')}">`;
            html += '<div class="tpak-file-upload-preview">';
            
            if (value) {
                html += `
                    <div class="tpak-file-preview">
                        <span class="dashicons dashicons-paperclip"></span>
                        <span class="tpak-file-name">${this.getFileName(value)}</span>
                        <button type="button" class="tpak-file-remove">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                `;
            } else {
                html += `
                    <div class="tpak-file-upload-placeholder">
                        <span class="dashicons dashicons-upload"></span>
                        <p>คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</p>
                        <p class="tpak-file-upload-info">
                            รองรับไฟล์: ${allowedTypes.join(', ')}<br>
                            ขนาดสูงสุด: ${this.formatFileSize(maxSize)}
                        </p>
                    </div>
                `;
            }
            
            html += '</div>';
            html += `
                <input type="file" 
                       id="tpak_${questionData.code}"
                       name="${questionData.code}"
                       class="tpak-file-input"
                       accept=".${allowedTypes.join(',.')}"
                       style="display: none;">
                <input type="hidden" 
                       name="${questionData.code}_current"
                       value="${value}"
                       class="tpak-file-current">
            `;
            html += '</div>';
            
            html += `
                <div class="tpak-file-upload-progress" style="display: none;">
                    <div class="tpak-progress-bar">
                        <div class="tpak-progress-fill" style="width: 0%"></div>
                    </div>
                    <span class="tpak-progress-text">0%</span>
                </div>
            `;
            html += '</div>';
            
            return html;
        }
        
        getFileName(filePath) {
            return filePath.split('/').pop() || filePath;
        }
        
        formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            bytes = Math.max(bytes, 0);
            const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
            const powMin = Math.min(pow, units.length - 1);
            
            bytes /= Math.pow(1024, powMin);
            
                    return Math.round(bytes * 100) / 100 + ' ' + units[powMin];
    }
}

class TPAKMatrixHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const subquestions = questionData.subquestions || [];
        const options = questionData.options || [];
        const matrixType = questionData.matrix_type || 'radio';
        
        if (subquestions.length === 0 || options.length === 0) {
            return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Question</div>';
        }
        
        let html = '<div class="tpak-matrix-container">';
        html += '<table class="tpak-matrix-table">';
        
        // Header
        html += '<thead><tr>';
        html += '<th class="tpak-matrix-subquestion-header">คำถาม</th>';
        options.forEach(option => {
            html += `<th class="tpak-matrix-option-header">${option.label}</th>`;
        });
        html += '</tr></thead>';
        
        // Body
        html += '<tbody>';
        subquestions.forEach(subquestion => {
            html += '<tr class="tpak-matrix-row">';
            html += `<td class="tpak-matrix-subquestion">${subquestion.label}</td>`;
            
            options.forEach(option => {
                const matrixKey = `${questionData.code}[${subquestion.code}]`;
                const value = responseData[matrixKey] || '';
                const checked = value === option.value ? 'checked' : '';
                
                html += '<td class="tpak-matrix-cell">';
                if (matrixType === 'checkbox') {
                    const checkboxKey = `${questionData.code}[${subquestion.code}][${option.value}]`;
                    const checkboxValue = responseData[checkboxKey] || '';
                    const checkboxChecked = checkboxValue === '1' ? 'checked' : '';
                    
                    html += `
                        <input type="checkbox" 
                               id="tpak_${questionData.code}_${subquestion.code}_${option.value}"
                               name="${questionData.code}[${subquestion.code}][${option.value}]"
                               value="1"
                               ${checkboxChecked}
                               class="tpak-matrix-checkbox">
                    `;
                } else {
                    html += `
                        <input type="radio" 
                               id="tpak_${questionData.code}_${subquestion.code}_${option.value}"
                               name="${questionData.code}[${subquestion.code}]"
                               value="${option.value}"
                               ${checked}
                               class="tpak-matrix-radio">
                    `;
                }
                html += `
                    <label for="tpak_${questionData.code}_${subquestion.code}_${option.value}" class="tpak-matrix-label">
                        ${option.label}
                    </label>
                `;
                html += '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        
        return html;
    }
}

class TPAKMatrixTextHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const subquestions = questionData.subquestions || [];
        const columns = questionData.columns || [];
        
        if (subquestions.length === 0 || columns.length === 0) {
            return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Text Question</div>';
        }
        
        let html = '<div class="tpak-matrix-text-container">';
        html += '<table class="tpak-matrix-text-table">';
        html += '<thead><tr>';
        html += '<th class="tpak-matrix-text-subquestion-header">คำถาม</th>';
        columns.forEach(column => {
            html += `<th class="tpak-matrix-text-column-header">${column.label}</th>`;
        });
        html += '</tr></thead>';
        
        html += '<tbody>';
        subquestions.forEach(subquestion => {
            html += '<tr class="tpak-matrix-text-row">';
            html += `<td class="tpak-matrix-text-subquestion">${subquestion.label}</td>`;
            
            columns.forEach(column => {
                const matrixKey = `${questionData.code}[${subquestion.code}][${column.code}]`;
                const value = responseData[matrixKey] || '';
                
                html += '<td class="tpak-matrix-text-cell">';
                html += `
                    <input type="text" 
                           id="tpak_${questionData.code}_${subquestion.code}_${column.code}"
                           name="${questionData.code}[${subquestion.code}][${column.code}]"
                           value="${value}"
                           class="tpak-matrix-text-input"
                           placeholder="${column.placeholder || 'กรุณาใส่คำตอบ'}">
                `;
                html += '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        
        return html;
    }
}

class TPAKMatrixNumericHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const subquestions = questionData.subquestions || [];
        const columns = questionData.columns || [];
        
        if (subquestions.length === 0 || columns.length === 0) {
            return '<div class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Numeric Question</div>';
        }
        
        let html = '<div class="tpak-matrix-numeric-container">';
        html += '<table class="tpak-matrix-numeric-table">';
        html += '<thead><tr>';
        html += '<th class="tpak-matrix-numeric-subquestion-header">คำถาม</th>';
        columns.forEach(column => {
            html += `<th class="tpak-matrix-numeric-column-header">${column.label}</th>`;
        });
        html += '</tr></thead>';
        
        html += '<tbody>';
        subquestions.forEach(subquestion => {
            html += '<tr class="tpak-matrix-numeric-row">';
            html += `<td class="tpak-matrix-numeric-subquestion">${subquestion.label}</td>`;
            
            columns.forEach(column => {
                const matrixKey = `${questionData.code}[${subquestion.code}][${column.code}]`;
                const value = responseData[matrixKey] || '';
                
                html += '<td class="tpak-matrix-numeric-cell">';
                html += `
                    <input type="number" 
                           id="tpak_${questionData.code}_${subquestion.code}_${column.code}"
                           name="${questionData.code}[${subquestion.code}][${column.code}]"
                           value="${value}"
                           class="tpak-matrix-numeric-input"
                           min="${column.min || ''}"
                           max="${column.max || ''}"
                           step="${column.step || '1'}"
                           placeholder="${column.placeholder || '0'}">
                `;
                html += '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        
        return html;
    }
}

class TPAKSliderHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const value = responseData[questionData.code] || '';
        const minValue = questionData.min_value || 0;
        const maxValue = questionData.max_value || 100;
        const step = questionData.step || 1;
        const defaultValue = questionData.default_value || minValue;
        const unit = questionData.unit || '';
        
        const currentValue = value || defaultValue;
        
        let html = '<div class="tpak-slider-container">';
        html += '<div class="tpak-slider-track">';
        html += `
            <input type="range" 
                   id="tpak_${questionData.code}"
                   name="${questionData.code}"
                   min="${minValue}"
                   max="${maxValue}"
                   step="${step}"
                   value="${currentValue}"
                   class="tpak-slider-input">
        `;
        html += `
            <div class="tpak-slider-labels">
                <span class="tpak-slider-min">${minValue}</span>
                <span class="tpak-slider-max">${maxValue}</span>
            </div>
        `;
        html += '</div>';
        
        html += `
            <div class="tpak-slider-value">
                <span class="tpak-slider-current">${currentValue}</span>
                ${unit ? `<span class="tpak-slider-unit">${unit}</span>` : ''}
            </div>
        `;
        html += '</div>';
        
        return html;
    }
}

class TPAKDropdownHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const value = responseData[questionData.code] || '';
        const options = questionData.options || [];
        
        if (options.length === 0) {
            return '<div class="tpak-error">ไม่พบตัวเลือกสำหรับ Dropdown Question</div>';
        }
        
        let html = '<div class="tpak-dropdown-container">';
        html += `
            <select id="tpak_${questionData.code}"
                    name="${questionData.code}"
                    class="tpak-dropdown-select">
                <option value="">-- เลือกตัวเลือก --</option>
        `;
        
        options.forEach(option => {
            const selected = value === option.value ? 'selected' : '';
            html += `
                <option value="${option.value}" ${selected}>
                    ${option.label}
                </option>
            `;
        });
        
        html += '</select></div>';
        return html;
    }
}

class TPAKListCommentHandler extends TPAKQuestionHandler {
    renderQuestion(questionData, responseData) {
        const value = responseData[questionData.code] || '';
        const commentValue = responseData[`${questionData.code}_comment`] || '';
        const options = questionData.options || [];
        
        if (options.length === 0) {
            return '<div class="tpak-error">ไม่พบตัวเลือกสำหรับ List with Comment Question</div>';
        }
        
        let html = '<div class="tpak-list-comment-container">';
        html += '<div class="tpak-list-comment-options">';
        
        options.forEach(option => {
            const checked = value === option.value ? 'checked' : '';
            html += `
                <div class="tpak-list-comment-item">
                    <input type="radio" 
                           id="tpak_${questionData.code}_${option.value}"
                           name="${questionData.code}"
                           value="${option.value}"
                           ${checked}
                           class="tpak-list-comment-radio">
                    <label for="tpak_${questionData.code}_${option.value}" class="tpak-list-comment-label">
                        ${option.label}
                    </label>
                </div>
            `;
        });
        
        html += '</div>';
        html += `
            <div class="tpak-list-comment-text">
                <label for="tpak_${questionData.code}_comment" class="tpak-list-comment-text-label">
                    หมายเหตุหรือความคิดเห็นเพิ่มเติม:
                </label>
                <textarea id="tpak_${questionData.code}_comment"
                          name="${questionData.code}_comment"
                          class="tpak-list-comment-textarea"
                          placeholder="กรุณาใส่ความคิดเห็นเพิ่มเติม (ถ้ามี)"
                          rows="3">${commentValue}</textarea>
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