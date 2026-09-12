document.addEventListener('DOMContentLoaded', function () {
	const app = document.querySelector('.sy-duftberater-app');

	if (!app) {
		return;
	}

	const steps = Array.from(app.querySelectorAll('.sy-duftberater-step'));
	const progressFill = app.querySelector('.sy-duftberater-progress-fill');
	const progressLabelCurrent = app.querySelector('[data-progress-current]');
	const progressLabelCurrentSecondary = app.querySelector('[data-progress-current-secondary]');
	const progressLabelTotal = app.querySelector('[data-progress-total]');
	const progressStepLabel = app.querySelector('[data-progress-step-label]');
	const progressOfLabel = app.querySelector('[data-progress-of-label]');
	const btnStart = app.querySelector('[data-action="start"]');
	const resultsContainer = app.querySelector('[data-results-container]');
	const selectedAnswersContainers = Array.from(app.querySelectorAll('.sy-selected-answers-target, #sy-selected-answers')).filter(function(el, idx, arr){ return arr.indexOf(el)===idx; });
	const progressWrap = app.querySelector('.sy-duftberater-progress-wrap');

	let currentStep = 0;
	const sydbDefaultLang = (['de','en','ar'].indexOf(app.dataset.lang || '') !== -1) ? app.dataset.lang : 'de';
	let currentLang = sydbDefaultLang;
	let state = { lang: currentLang };
	let sydbHasUserGesture = false;
	let sydbQuestionAudio = null;
	let sydbPopupAudio = null;
	const sydbAudioDisabled = (typeof syDuftberaterData !== 'undefined' && syDuftberaterData.audio && String(syDuftberaterData.audio.disabled) === '1');
	const sydbClickSoundEnabled = (!sydbAudioDisabled && typeof syDuftberaterData !== 'undefined' && syDuftberaterData.audio && String(syDuftberaterData.audio.clickSoundEnabled) !== '0');
	const sydbClickSoundUrl = (sydbClickSoundEnabled && typeof syDuftberaterData !== 'undefined' && syDuftberaterData.audio && syDuftberaterData.audio.clickSoundUrl) ? syDuftberaterData.audio.clickSoundUrl : '';
	const sydbAutoDownloadEnabled = (typeof syDuftberaterData === 'undefined' || String(syDuftberaterData.autoDownloadEnabled) !== '0');
	let sydbClickAudio = null;
	if (sydbClickSoundUrl) {
		try {
			sydbClickAudio = new Audio(sydbClickSoundUrl);
			sydbClickAudio.preload = 'auto';
			sydbClickAudio.volume = 0.45;
		} catch (e) {
			sydbClickAudio = null;
		}
	}

	function unlockAudioGesture() {
		sydbHasUserGesture = true;
	}

	function playClickSound() {
		unlockAudioGesture();
		if (sydbAudioDisabled) return;
		if (!sydbClickAudio) return;
		try {
			sydbClickAudio.pause();
			sydbClickAudio.currentTime = 0;
			const p = sydbClickAudio.play();
			if (p && typeof p.catch === 'function') { p.catch(function(){}); }
		} catch (e) {}
	}

	function stopQuestionAudio() {
		if (!sydbQuestionAudio) return;
		try {
			sydbQuestionAudio.pause();
			sydbQuestionAudio.currentTime = 0;
		} catch (e) {}
	}

	function stopPopupAudio() {
		if (!sydbPopupAudio) return;
		try {
			sydbPopupAudio.pause();
			sydbPopupAudio.currentTime = 0;
		} catch (e) {}
	}

	function playCompletionAudio() {
		stopQuestionAudio();
		stopPopupAudio();
		if (sydbAudioDisabled) return;
		if (!sydbHasUserGesture || typeof syDuftberaterData === 'undefined' || !syDuftberaterData.audio || !syDuftberaterData.audio.completion) return;
		const map = syDuftberaterData.audio.completion || {};
		const url = map[currentLang] || map.de || '';
		if (!url) return;
		try {
			sydbPopupAudio = new Audio(url);
			sydbPopupAudio.preload = 'auto';
			sydbPopupAudio.volume = 0.9;
			const p = sydbPopupAudio.play();
			if (p && typeof p.catch === 'function') { p.catch(function(){}); }
		} catch (e) {}
	}

	function playQuestionAudioForStep(step) {
		stopQuestionAudio();
		if (sydbAudioDisabled) return;
		if (!step || (step.dataset.type !== 'question' && step.dataset.type !== 'welcome' && step.dataset.type !== 'result')) return;
		const url = step.getAttribute('data-audio-' + currentLang) || step.getAttribute('data-audio-url') || step.getAttribute('data-audio-de') || '';
		if (!url || !sydbHasUserGesture) return;
		try {
			sydbQuestionAudio = new Audio(url);
			sydbQuestionAudio.preload = 'auto';
			sydbQuestionAudio.volume = 0.9;
			const p = sydbQuestionAudio.play();
			if (p && typeof p.catch === 'function') { p.catch(function(){}); }
		} catch (e) {}
	}

	function playUsageStageAudio(step, stage) {
		if (sydbAudioDisabled) return;
		if (!step || step.dataset.key !== 'verwendungsbereich' || !stage) return;
		stopQuestionAudio();
		if (stage === 'main') return;
		const url = step.getAttribute('data-usage-audio-' + stage + '-' + currentLang) || step.getAttribute('data-usage-audio-' + stage + '-de') || '';
		if (!url || !sydbHasUserGesture) return;
		try {
			sydbQuestionAudio = new Audio(url);
			sydbQuestionAudio.preload = 'auto';
			sydbQuestionAudio.volume = 0.9;
			const p = sydbQuestionAudio.play();
			if (p && typeof p.catch === 'function') { p.catch(function(){}); }
		} catch (e) {}
	}

	const usageLeafValues = ['zuhause', 'ausgehen', 'buero', 'grosse_raeume', 'meeting', 'date', 'private_anlaesse'];
	const usageQuestionFlow = {
		main: {
			kicker: { de: 'Schritt 3', en: 'Step 3', ar: 'الخطوة 3' },
			title: { de: 'Für welchen Gebrauch / Verwendungsbereich suchst du den Duft?', en: 'What occasion or use is the fragrance for?', ar: 'لأي استخدام أو مناسبة تبحث عن العطر؟' },
			text: { de: 'Wähle zuerst die Hauptsituation. Danach erzeugt der Berater automatisch die nächste passende Folgefrage.', en: 'First choose the main situation. The advisor then shows the right follow-up question.', ar: 'اختر الحالة الأساسية أولاً، ثم سيعرض المستشار السؤال المناسب التالي.' }
		},
		alltag: {
			kicker: { de: 'Schritt 3A', en: 'Step 3A', ar: 'الخطوة 3A' },
			title: { de: 'Welche Alltagssituation passt am besten?', en: 'Which everyday situation fits best?', ar: 'أي حالة يومية تناسب أكثر؟' },
			text: { de: 'Du hast Alltag gewählt. Jetzt kommt nur die nächste passende Frage für Alltag.', en: 'You chose everyday use. Now only the matching follow-up question appears.', ar: 'اخترت الاستخدام اليومي. الآن يظهر السؤال المناسب فقط.' }
		},
		arbeit: {
			kicker: { de: 'Schritt 3B', en: 'Step 3B', ar: 'الخطوة 3B' },
			title: { de: 'In welchem Arbeitsumfeld wird der Duft getragen?', en: 'In which work setting will it be worn?', ar: 'في أي بيئة عمل سيتم استخدام العطر؟' },
			text: { de: 'Du hast Arbeit gewählt. Jetzt kommt nur die passende Detailfrage für das Arbeitsumfeld.', en: 'You chose work. Now the work setting is refined.', ar: 'اخترت العمل. الآن نحدد بيئة العمل بدقة.' }
		},
		formell: {
			kicker: { de: 'Schritt 3A', en: 'Step 3A', ar: 'الخطوة 3A' },
			title: { de: 'Welche Art von formellem Anlass ist gemeint?', en: 'Which formal occasion do you mean?', ar: 'ما نوع المناسبة الرسمية؟' },
			text: { de: 'Du hast Formell / wichtige Anlässe gewählt. Jetzt wählst du nur noch den genauen Anlass.', en: 'You chose formal / important occasions. Now choose the exact occasion.', ar: 'اخترت مناسبة رسمية / مهمة. الآن اختر المناسبة بالتحديد.' }
		}
	};

	function t(values) {
		if (values && typeof values === 'object') {
			return values[currentLang] || values.de || '';
		}
		return values || '';
	}

	function getDataText(el) {
		if (!el) return '';
		return el.getAttribute('data-' + currentLang) || el.getAttribute('data-de') || el.textContent || '';
	}

	function getUi(key, fallback) {
		if (typeof syDuftberaterData !== 'undefined' && syDuftberaterData.ui && syDuftberaterData.ui[currentLang] && syDuftberaterData.ui[currentLang][key]) {
			return syDuftberaterData.ui[currentLang][key];
		}
		return fallback || '';
	}

	let sydbNonceRefreshPromise = null;

	function refreshSydbNonce() {
		if (typeof syDuftberaterData === 'undefined' || !syDuftberaterData.ajaxUrl) {
			return Promise.reject(new Error('AJAX URL fehlt'));
		}
		if (sydbNonceRefreshPromise) {
			return sydbNonceRefreshPromise;
		}
		const action = syDuftberaterData.nonceAction || 'sy_duftberater_refresh_nonce';
		const sep = syDuftberaterData.ajaxUrl.indexOf('?') === -1 ? '?' : '&';
		const url = syDuftberaterData.ajaxUrl + sep + 'action=' + encodeURIComponent(action) + '&_sydb=' + Date.now();
		sydbNonceRefreshPromise = fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Cache-Control': 'no-cache' }
		}).then(function(response){
			return response.text().then(function(text){
				let data = null;
				try { data = JSON.parse(text); } catch (e) {}
				if (!response.ok || !data || !data.success || !data.data || !data.data.nonce) {
					throw new Error('Nonce konnte nicht erneuert werden');
				}
				syDuftberaterData.nonce = data.data.nonce;
				return data.data.nonce;
			});
		}).finally(function(){
			sydbNonceRefreshPromise = null;
		});
		return sydbNonceRefreshPromise;
	}

	function sydbAjaxRequest(formData, allowRetry) {
		if (typeof syDuftberaterData === 'undefined' || !syDuftberaterData.ajaxUrl) {
			return Promise.reject(new Error('AJAX URL fehlt'));
		}
		formData.set('nonce', syDuftberaterData.nonce || '');
		return fetch(syDuftberaterData.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Cache-Control': 'no-cache' }
		}).then(function(response){
			return response.text().then(function(text){
				const trimmed = String(text || '').trim();
				const nonceExpired = response.status === 403 || trimmed === '-1';
				if (nonceExpired && allowRetry !== false) {
					return refreshSydbNonce().then(function(){
						formData.set('nonce', syDuftberaterData.nonce || '');
						return sydbAjaxRequest(formData, false);
					});
				}
				let data = null;
				try { data = JSON.parse(trimmed); } catch (e) {}
				if (!response.ok || !data) {
					throw new Error('Ungültige Serverantwort');
				}
				return data;
			});
		});
	}

	function clearLeadFormsAndResultState() {
		Array.from(app.querySelectorAll('[data-lead-form]')).forEach(function(form){
			try { form.reset(); } catch (e) {}
			Array.from(form.querySelectorAll('input, textarea, select')).forEach(function(field){
				if (field.type === 'checkbox' || field.type === 'radio') {
					field.checked = false;
				} else {
					field.value = '';
					field.defaultValue = '';
					try { field.setAttribute('value', ''); } catch (e) {}
				}
			});
			const box = form.closest('[data-lead-box]');
			const msg = box ? box.querySelector('[data-lead-message]') : null;
			if (msg) {
				msg.className = 'sy-duftberater-lead-message';
				msg.innerHTML = '';
			}
		});
		if (resultsContainer) {
			resultsContainer.innerHTML = '<div class="sy-duftberater-loading">' + getUi('loading_text', 'PDF wird erstellt …') + '</div>';
		}
	}

	function resetAdvisorToStart() {
		stopQuestionAudio();
		stopPopupAudio();
		clearLeadFormsAndResultState();
		applyLanguage(sydbDefaultLang);
		state = { lang: sydbDefaultLang };
		currentLang = sydbDefaultLang;
		app.dataset.lang = sydbDefaultLang;
		app.setAttribute('dir', sydbDefaultLang === 'ar' ? 'rtl' : 'ltr');
		app.classList.toggle('is-rtl', sydbDefaultLang === 'ar');
		steps.forEach(function (item) {
			syncSelectedUI(item);
			applyDynamicStepPresentation(item);
			resetAnswerPagination(item);
			resetUsageFlow(item);
		});
		updateSelectedAnswers();
		showStep(0);
	}

	function ensureCompletionPopup() {
		let modal = app.querySelector('[data-completion-popup]');
		if (modal) return modal;
		modal = document.createElement('div');
		modal.className = 'sy-duftberater-completion-popup';
		modal.setAttribute('data-completion-popup', '');
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML = '<div class="sy-duftberater-completion-backdrop"></div><div class="sy-duftberater-completion-dialog" role="dialog" aria-modal="true"><div class="sy-duftberater-completion-logo-wrap" data-popup-logo-wrap></div><p class="sy-duftberater-completion-text" data-popup-text></p><button type="button" class="sy-duftberater-btn sy-duftberater-btn-primary" data-popup-restart></button></div>';
		app.appendChild(modal);
		const restart = modal.querySelector('[data-popup-restart]');
		if (restart) {
			restart.addEventListener('click', function(){
				playClickSound();
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				resetAdvisorToStart();
			});
		}
		return modal;
	}

	function showCompletionPopup() {
		const modal = ensureCompletionPopup();
		const logoWrap = modal.querySelector('[data-popup-logo-wrap]');
		const textEl = modal.querySelector('[data-popup-text]');
		const restart = modal.querySelector('[data-popup-restart]');
		if (logoWrap) {
			logoWrap.innerHTML = '';
			const logoUrl = (typeof syDuftberaterData !== 'undefined' && syDuftberaterData.logoUrl) ? syDuftberaterData.logoUrl : '';
			if (logoUrl) {
				const img = document.createElement('img');
				img.src = logoUrl;
				img.alt = 'Alowidat';
				logoWrap.appendChild(img);
			}
		}
		if (textEl) {
			textEl.textContent = getUi('completion_popup_text', currentLang === 'ar' ? 'شكرًا لاستخدامك مستشارك الذكي للعطور من العوايدات. نتمنى لك تجربة عطرية مميزة مع العوايدات.' : (currentLang === 'en' ? 'Thank you for using the smart fragrance advisor from Alowidat. We wish you a special fragrance experience with Alowidat.' : 'Vielen Dank, dass du den intelligenten Duftberater von Alowidat genutzt hast. Wir wünschen dir ein besonderes Dufterlebnis mit Alowidat.'));
		}
		if (restart) {
			restart.textContent = getUi('completion_popup_restart', currentLang === 'ar' ? 'ابدأ من جديد' : (currentLang === 'en' ? 'Start again' : 'Erneut starten'));
		}
		modal.setAttribute('dir', currentLang === 'ar' ? 'rtl' : 'ltr');
		modal.classList.toggle('is-rtl', currentLang === 'ar');
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		playCompletionAudio();
	}

	function updateStaticLanguageParts() {
		if (progressStepLabel) { progressStepLabel.textContent = getUi('progress_step', currentLang === 'ar' ? 'الخطوة' : (currentLang === 'en' ? 'Step' : 'Schritt')); }
		if (progressOfLabel) { progressOfLabel.textContent = getUi('progress_of', currentLang === 'ar' ? 'من' : (currentLang === 'en' ? 'of' : 'von')); }
		Array.from(app.querySelectorAll('[data-step-kicker][data-step-order]')).forEach(function(kicker){
			var step = kicker.closest('.sy-duftberater-step');
			if (step && step.dataset && step.dataset.key === 'verwendungsbereich' && step.dataset.usageStage && step.dataset.usageStage !== 'main') {
				return;
			}
			var order = kicker.getAttribute('data-step-order') || '';
			kicker.textContent = getUi('progress_step', currentLang === 'ar' ? 'الخطوة' : (currentLang === 'en' ? 'Step' : 'Schritt')) + (order ? ' ' + order : '');
		});
	}

	function applyLanguage(lang) {
		currentLang = ['de','en','ar'].indexOf(lang) !== -1 ? lang : 'de';
		state.lang = currentLang;
		app.dataset.lang = currentLang;
		app.setAttribute('dir', currentLang === 'ar' ? 'rtl' : 'ltr');
		app.classList.toggle('is-rtl', currentLang === 'ar');
		Array.from(app.querySelectorAll('[data-de], [data-en], [data-ar]')).forEach(function(el){
			if (el.classList.contains('sy-duftberater-option-card')) return;
			const text = getDataText(el);
			if (text) el.textContent = text;
		});
		Array.from(app.querySelectorAll('.sy-duftberater-option-card')).forEach(function(card){
			const label = card.getAttribute('data-label-' + currentLang) || card.getAttribute('data-label-de') || card.dataset.label || '';
			card.dataset.label = label;
			const title = card.querySelector('.sy-duftberater-option-title');
			if (title) title.textContent = getDataText(title) || label;
			const subtitle = card.querySelector('.sy-duftberater-option-subtitle');
			if (subtitle) subtitle.textContent = getDataText(subtitle) || subtitle.textContent;
		});
		rebuildOptionLabels();
		updateStaticLanguageParts();
		updateProgress();
		updateSelectedAnswers();
	}

	const totalQuestionSteps = steps.filter(function (step) {
		return step.dataset.type === 'question';
	}).length;

	const metaConfig = {
		geschlecht: { icon: '👤', shortLabel: 'Geschlecht' },
		jahreszeit: { icon: '🌦️', shortLabel: 'Jahreszeit' },
		verwendungsbereich: { icon: '🎯', shortLabel: 'Anlass' },
		raucher: { icon: '🚬', shortLabel: 'Raucher' },
		alter: { icon: '🧭', shortLabel: 'Alter' },
		duftrichtungen: { icon: '✨', shortLabel: 'Duftrichtungen' },
		duftnoten: { icon: '❌', shortLabel: 'Nicht gewollte Noten' }
	};

	let optionLabels = {};
	function rebuildOptionLabels() {
		optionLabels = {};
		steps.filter(function (step) {
		return step.dataset.type === 'question';
	}).forEach(function (step) {
		const key = step.dataset.key;
		optionLabels[key] = {};
		Array.from(step.querySelectorAll('.sy-duftberater-option-card')).forEach(function (card) {
			optionLabels[key][card.dataset.value] = card.dataset.label || card.textContent.trim();
		});
		});
	}
	rebuildOptionLabels();


	function getUsageStep() {
		return steps.find(function (step) { return step.dataset.key === 'verwendungsbereich'; }) || null;
	}

	function resetUsageFlow(step) {
		if (!step || step.dataset.key !== 'verwendungsbereich') {
			return;
		}
		step.dataset.usageStage = 'main';
		Array.from(step.querySelectorAll('[data-usage-stage]')).forEach(function (section) {
			section.classList.toggle('is-active', section.dataset.usageStage === 'main');
		});
		updateUsageQuestionCopy(step, 'main');
		updateUsageCrumbs(step);
	}

	function getUsageDisplayPath() {
		if (!state.verwendungsbereich_path || !Array.isArray(state.verwendungsbereich_path)) {
			return [];
		}
		return state.verwendungsbereich_path.filter(Boolean);
	}

	function setUsagePath(pathValues) {
		const usageStep = getUsageStep();
		const labels = usageStep ? optionLabels.verwendungsbereich || {} : {};
		state.verwendungsbereich_path = pathValues.map(function (value) {
			return labels[value] || value;
		});
	}

	function showUsageStage(step, stage) {
		if (!step || step.dataset.key !== 'verwendungsbereich') {
			return;
		}
		step.dataset.usageStage = stage;
		Array.from(step.querySelectorAll('[data-usage-stage]')).forEach(function (section) {
			section.classList.toggle('is-active', section.dataset.usageStage === stage);
		});
		updateUsageQuestionCopy(step, stage);
		updateUsageCrumbs(step);
		playUsageStageAudio(step, stage);
	}


	function updateUsageQuestionCopy(step, stage) {
		if (!step || step.dataset.key !== 'verwendungsbereich') {
			return;
		}
		const cfg = usageQuestionFlow[stage] || usageQuestionFlow.main;
		const kicker = step.querySelector('[data-step-kicker]');
		const title = step.querySelector('[data-step-title]');
		const text = step.querySelector('[data-step-text]');
		if (kicker) kicker.textContent = t(cfg.kicker);
		if (title) title.textContent = t(cfg.title);
		if (text) text.textContent = t(cfg.text);
	}

	function updateUsageCrumbs(step) {
		if (!step || step.dataset.key !== 'verwendungsbereich') {
			return;
		}
		const wrap = step.querySelector('[data-usage-crumbs]');
		if (!wrap) return;
		wrap.innerHTML = '';
		const path = getUsageDisplayPath();
		if (!path.length) return;
		path.forEach(function(label){
			const chip = document.createElement('span');
			chip.className = 'sy-duftberater-usage-crumb';
			chip.textContent = label;
			wrap.appendChild(chip);
		});
	}

	function syncUsageUI(step) {
		if (!step || step.dataset.key !== 'verwendungsbereich') {
			return;
		}
		const selected = state.verwendungsbereich || '';
		const main = state.verwendungsbereich_main || '';
		const branch = state.verwendungsbereich_branch || '';
		Array.from(step.querySelectorAll('.sy-duftberater-option-card')).forEach(function (card) {
			let role = card.dataset.usageRole || '';
			if (!role) {
				const stageEl = card.closest('[data-usage-stage]');
				role = stageEl ? stageEl.dataset.usageStage : '';
			}
			let isSelected = false;
			if (role === 'main') {
				isSelected = card.dataset.value === main;
			} else if (role === 'alltag') {
				isSelected = card.dataset.value === branch;
			} else if (role === 'arbeit') {
				isSelected = card.dataset.value === selected;
			} else if (role === 'formell') {
				isSelected = card.dataset.value === selected;
			}
			card.classList.toggle('is-selected', isSelected);
			card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
		});
	}

	function handleUsageCardClick(step, card) {
		let role = card.dataset.usageRole || '';
		const value = card.dataset.value || '';
		if (!role) {
			const stageEl = card.closest('[data-usage-stage]');
			role = stageEl ? stageEl.dataset.usageStage : '';
		}
		if (role === 'main') {
			state.verwendungsbereich = '';
			state.verwendungsbereich_main = value;
			state.verwendungsbereich_branch = '';
			setUsagePath([value]);
			syncUsageUI(step);
			showUsageStage(step, value === 'alltag' ? 'alltag' : 'formell');
			updateSelectedAnswers();
			return;
		}
		if (role === 'alltag') {
			state.verwendungsbereich_branch = value;
			if (value === 'arbeit') {
				state.verwendungsbereich = '';
				setUsagePath(['alltag', 'arbeit']);
				syncUsageUI(step);
				showUsageStage(step, 'arbeit');
				updateSelectedAnswers();
				return;
			}
			state.verwendungsbereich = value;
			setUsagePath(['alltag', value]);
			syncUsageUI(step);
			updateSelectedAnswers();
			window.setTimeout(function () { nextStep(); }, 170);
			return;
		}
		if (role === 'arbeit') {
			state.verwendungsbereich = value;
			setUsagePath(['alltag', 'arbeit', value]);
			syncUsageUI(step);
			updateSelectedAnswers();
			window.setTimeout(function () { nextStep(); }, 170);
			return;
		}
		if (role === 'formell') {
			state.verwendungsbereich = value;
			state.verwendungsbereich_branch = value;
			setUsagePath(['formell', value]);
			syncUsageUI(step);
			updateSelectedAnswers();
			window.setTimeout(function () { nextStep(); }, 170);
		}
	}

	function getQuestionStepIndex(stepIndex) {
		return steps.slice(0, stepIndex + 1).filter(function (step) {
			return step.dataset.type === 'question';
		}).length;
	}

	function isMultiStep(step) {
		return step && step.dataset.multiple === '1';
	}

	function isRequiredStep(step) {
		return !step || step.dataset.required !== '0';
	}

	function getActiveStep() {
		return steps[currentStep] || null;
	}

	function scrollAppTop() {
		const rect = app.getBoundingClientRect();
		const top = rect.top + window.pageYOffset - 24;
		window.scrollTo({ top: top, behavior: 'smooth' });
	}

	function resetInnerScroll() {
		const activeStep = getActiveStep();
		if (!activeStep) {
			return;
		}

		const options = activeStep.querySelector('.sy-duftberater-options');
		const results = activeStep.querySelector('.sy-duftberater-results');

		if (options) {
			options.scrollTop = 0;
		}

		if (results) {
			results.scrollTop = 0;
		}
	}

	function updateProgress() {
		updateStaticLanguageParts();
		const activeStep = getActiveStep();

		if (!activeStep || activeStep.dataset.type !== 'question') {
			if (progressFill) {
				progressFill.style.width = '0%';
			}
			if (app) { app.classList.add('is-start-view'); }
			return;
		}

		if (app) { app.classList.remove('is-start-view'); }

		const currentQuestion = getQuestionStepIndex(currentStep);
		const percentage = totalQuestionSteps > 0 ? (currentQuestion / totalQuestionSteps) * 100 : 0;

		if (progressFill) {
			progressFill.style.width = percentage + '%';
		}

		if (progressLabelCurrent) {
			progressLabelCurrent.textContent = String(currentQuestion);
		}

		if (progressLabelCurrentSecondary) {
			progressLabelCurrentSecondary.textContent = String(currentQuestion);
		}

		if (progressLabelTotal) {
			progressLabelTotal.textContent = String(totalQuestionSteps);
		}
	}

	function getStepValue(step) {
		const key = step.dataset.key;
		return key ? state[key] || null : null;
	}

	function setStepValue(step, value) {
		const key = step.dataset.key;
		if (key) {
			state[key] = value;
		}
	}

	function syncSelectedUI(step) {
		if (step && step.dataset.key === 'verwendungsbereich') {
			syncUsageUI(step);
			return;
		}

		const value = getStepValue(step);
		const cards = Array.from(step.querySelectorAll('.sy-duftberater-option-card'));

		cards.forEach(function (card) {
			const cardValue = card.dataset.value;
			let selected = false;

			if (Array.isArray(value)) {
				selected = value.includes(cardValue);
			} else {
				selected = value === cardValue;
			}

			card.classList.toggle('is-selected', selected);
			card.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
	}

	function updateSelectedAnswers() {
		if (!selectedAnswersContainers.length) {
			return;
		}

		selectedAnswersContainers.forEach(function(container){ container.innerHTML = ''; });
		const questionSteps = steps.filter(function (step) {
			return step.dataset.type === 'question';
		});

		let hasAnySelection = false;

		questionSteps.forEach(function (step) {
			const key = step.dataset.key;
			const rawValue = state[key];
			const usagePath = key === 'verwendungsbereich' ? getUsageDisplayPath() : [];

			if ((!rawValue || (Array.isArray(rawValue) && rawValue.length === 0)) && !usagePath.length) {
				return;
			}

			hasAnySelection = true;
			const values = key === 'verwendungsbereich' && usagePath.length ? usagePath : (Array.isArray(rawValue) ? rawValue : [rawValue]);

			values.forEach(function (value) {
				const label = optionLabels[key] && optionLabels[key][value] ? optionLabels[key][value] : value;
				selectedAnswersContainers.forEach(function (container) {
					const chip = document.createElement('span');
					chip.className = 'sy-duftberater-selection-chip';
					chip.textContent = label;
					chip.addEventListener('click', function () {
						const index = steps.findIndex(function (item) {
							return item.dataset.key === key;
						});
						if (index !== -1) {
							showStep(index);
						}
					});
					container.appendChild(chip);
				});
			});
		});

		if (!hasAnySelection) {
			const empty = document.createElement('div');
			empty.className = 'sy-duftberater-selection-empty';
			empty.textContent = getUi('selection_empty', currentLang === 'ar' ? 'ستظهر إجاباتك المختارة هنا بعد الخطوة الأولى.' : (currentLang === 'en' ? 'Your selected answers will appear here from the first step.' : 'Deine ausgewählten Antworten erscheinen hier ab dem ersten Schritt.'));
			selectedAnswersContainers.forEach(function(container){ container.appendChild(empty.cloneNode(true)); });
		}
	}

	function canGoNext() {
		const activeStep = getActiveStep();

		if (!activeStep || activeStep.dataset.type !== 'question') {
			return true;
		}

		if (!isRequiredStep(activeStep)) {
			return true;
		}

		if (activeStep.dataset.key === 'verwendungsbereich') {
			return usageLeafValues.indexOf(state.verwendungsbereich || '') !== -1;
		}

		const value = getStepValue(activeStep);

		if (isMultiStep(activeStep)) {
			return Array.isArray(value) && value.length > 0;
		}

		return !!value;
	}

	function nextStep() {
		if (!canGoNext()) {
			return;
		}

		const nextIndex = currentStep + 1;

		if (nextIndex < steps.length) {
			showStep(nextIndex);
		}
	}

	function prevStep() {
		const activeStep = getActiveStep();
		if (activeStep && activeStep.dataset.key === 'verwendungsbereich') {
			const stage = activeStep.dataset.usageStage || 'main';
			if (stage === 'arbeit') {
				state.verwendungsbereich = '';
				state.verwendungsbereich_branch = '';
				setUsagePath(['alltag']);
				syncUsageUI(activeStep);
				showUsageStage(activeStep, 'alltag');
				updateSelectedAnswers();
				return;
			}
			if (stage === 'alltag' || stage === 'formell') {
				state.verwendungsbereich = '';
				state.verwendungsbereich_main = '';
				state.verwendungsbereich_branch = '';
				state.verwendungsbereich_path = [];
				syncUsageUI(activeStep);
				showUsageStage(activeStep, 'main');
				updateSelectedAnswers();
				return;
			}
		}

		const prevIndex = currentStep - 1;
		if (prevIndex >= 0) {
			showStep(prevIndex);
		}
	}

	function uniquePush(target, values) {
		if (!Array.isArray(values)) {
			return target;
		}

		values.forEach(function (value) {
			if (target.indexOf(value) === -1) {
				target.push(value);
			}
		});

		return target;
	}

	function getRecommendations(stepKey) {
		const recommended = [];

		if (stepKey === 'jahreszeit') {
			if (state.geschlecht === 'herren') {
				uniquePush(recommended, ['herbst', 'winter', 'sommer', 'fruehling']);
			} else if (state.geschlecht === 'damen') {
				uniquePush(recommended, ['fruehling', 'sommer', 'herbst', 'winter']);
			}
		}

		if (stepKey === 'verwendungsbereich') {
			if (state.jahreszeit === 'sommer') {
				uniquePush(recommended, ['buero', 'ausgehen', 'date', 'zuhause', 'arbeit']);
			} else if (state.jahreszeit === 'winter') {
				uniquePush(recommended, ['date', 'private_anlaesse', 'ausgehen', 'meeting', 'grosse_raeume']);
			} else if (state.jahreszeit === 'fruehling') {
				uniquePush(recommended, ['zuhause', 'meeting', 'buero', 'date', 'arbeit']);
			} else if (state.jahreszeit === 'herbst') {
				uniquePush(recommended, ['meeting', 'ausgehen', 'private_anlaesse', 'grosse_raeume', 'arbeit']);
			}
		}

		if (stepKey === 'raucher') {
			if (state.verwendungsbereich === 'buero' || state.verwendungsbereich === 'meeting') {
				uniquePush(recommended, ['nein', 'ja']);
			} else {
				uniquePush(recommended, ['ja', 'nein']);
			}
		}

		if (stepKey === 'alter') {
			if (state.geschlecht === 'herren') {
				uniquePush(recommended, ['20_30', '30_40', 'ueber_40', '10_20']);
			} else if (state.geschlecht === 'damen') {
				uniquePush(recommended, ['20_30', '10_20', '30_40', 'ueber_40']);
			} else {
				uniquePush(recommended, ['20_30', '30_40', '10_20', 'ueber_40']);
			}
		}

		if (stepKey === 'duftrichtungen') {
			if (state.jahreszeit === 'sommer') {
				uniquePush(recommended, ['zitrisch', 'aquatisch', 'gruen', 'fruchtig', 'blumig']);
			} else if (state.jahreszeit === 'winter') {
				uniquePush(recommended, ['amber', 'wuerzig', 'holzig', 'oud', 'gourmand', 'ledrig']);
			} else if (state.jahreszeit === 'fruehling') {
				uniquePush(recommended, ['blumig', 'gruen', 'zitrisch', 'fruchtig']);
			} else if (state.jahreszeit === 'herbst') {
				uniquePush(recommended, ['holzig', 'wuerzig', 'amber', 'ledrig', 'oud', 'gourmand']);
			}

			if (state.verwendungsbereich === 'buero' || state.verwendungsbereich === 'meeting') {
				uniquePush(recommended, ['zitrisch', 'gruen', 'aquatisch', 'blumig']);
			} else if (state.verwendungsbereich === 'date') {
				uniquePush(recommended, ['amber', 'wuerzig', 'holzig', 'gourmand', 'ledrig']);
			} else if (state.verwendungsbereich === 'grosse_raeume') {
				uniquePush(recommended, ['holzig', 'wuerzig', 'amber', 'ledrig']);
			} else if (state.verwendungsbereich === 'zuhause') {
				uniquePush(recommended, ['blumig', 'gourmand', 'gruen', 'amber']);
			}

			if (state.geschlecht === 'herren') {
				uniquePush(recommended, ['holzig', 'wuerzig', 'aquatisch', 'ledrig', 'oud']);
			} else if (state.geschlecht === 'damen') {
				uniquePush(recommended, ['blumig', 'fruchtig', 'gourmand', 'amber']);
			}
		}

		if (stepKey === 'duftnoten') {
			return [];
		}

		return recommended;
	}

	function getPriorityScore(stepKey, optionValue) {
		let score = 0;
		const recommended = getRecommendations(stepKey);

		const foundIndex = recommended.indexOf(optionValue);
		if (foundIndex !== -1) {
			score += 100 - foundIndex;
		}

		const currentValue = state[stepKey];
		if (Array.isArray(currentValue) && currentValue.indexOf(optionValue) !== -1) {
			score += 500;
		} else if (currentValue === optionValue) {
			score += 500;
		}

		return score;
	}

	function getDynamicHint(stepKey) {
		if (stepKey === 'duftnoten') {
			return '';
		}

		if (stepKey === 'duftrichtungen') {
			if (state.verwendungsbereich === 'buero' || state.verwendungsbereich === 'meeting') {
				return getUi('hint_duftrichtungen_office', currentLang === 'ar' ? 'للمكتب والاجتماعات يعطي المستشار أولوية للعطور النظيفة والأنيقة وغير القوية جداً.' : (currentLang === 'en' ? 'For office and meetings, the advisor prioritizes clean, polished and not too intense profiles.' : 'Für Büro und Meetings priorisiert der Berater eher klare, gepflegte und nicht zu aggressive Profile.'));
			}

			if (state.verwendungsbereich === 'date') {
				return getUi('hint_duftrichtungen_date', currentLang === 'ar' ? 'للمواعيد يعطي المستشار أولوية للعطور الجذابة والحسية ذات الطابع الواضح.' : (currentLang === 'en' ? 'For dates, the advisor prioritizes distinctive and sensual fragrance profiles.' : 'Für Dates priorisiert der Berater charakterstarke und sinnliche Duftprofile.'));
			}
		}

		if (stepKey === 'verwendungsbereich') {
			if (state.jahreszeit === 'sommer') {
				return getUi('hint_usage_sommer', currentLang === 'ar' ? 'في الصيف تكون الاستخدامات اليومية والمكتب والمناسبات الخفيفة غالباً أنسب.' : (currentLang === 'en' ? 'In summer, everyday use, office and light leisure occasions are usually prioritized.' : 'Im Sommer stehen Alltag, Büro und leichte Freizeit-Anlässe meist weiter oben.'));
			}

			if (state.jahreszeit === 'winter') {
				return getUi('hint_usage_winter', currentLang === 'ar' ? 'في الشتاء تكون الاستخدامات القوية والمسائية والرسمية غالباً أكثر مناسبة.' : (currentLang === 'en' ? 'In winter, stronger evening and more formal occasions are often prioritized.' : 'Im Winter rücken starke, abendliche und formellere Einsatzbereiche oft weiter nach oben.'));
			}
		}

		return '';
	}

	function applyDynamicStepPresentation(step) {
		if (!step || step.dataset.type !== 'question') {
			return;
		}

		const stepKey = step.dataset.key || '';
		const cards = Array.from(step.querySelectorAll('.sy-duftberater-option-card'));
		const recommended = getRecommendations(stepKey);

		if (cards.length > 0) {
			cards.forEach(function (card) {
				card.classList.remove('is-recommended', 'is-soft');
				if (recommended.indexOf(card.dataset.value) !== -1) {
					card.classList.add('is-recommended');
				}
			});
		}

		const hint = step.querySelector('.sy-duftberater-hint');
		if (hint) {
			const defaultHint = hint.dataset.defaultHint || hint.textContent;
			hint.dataset.defaultHint = defaultHint;
			const dynamicHint = getDynamicHint(stepKey);
			const finalHint = dynamicHint || defaultHint;
			hint.textContent = finalHint;
			hint.style.display = finalHint ? '' : 'none';
		}
	}

	function updateQuestionNextButton(step) {
		if (!step || step.dataset.type !== 'question') return;
		const btn = step.querySelector('[data-action="next"]');
		if (!btn) return;
		const stepIndex = steps.indexOf(step);
		const next = steps[stepIndex + 1] || null;
		const showResults = next && next.dataset.type === 'result';
		btn.textContent = showResults ? getUi('show_results_button', currentLang === 'ar' ? 'عرض النتائج' : (currentLang === 'en' ? 'Show results' : 'Ergebnisse anzeigen')) : getUi('next_button', currentLang === 'ar' ? 'التالي' : (currentLang === 'en' ? 'Next' : 'Weiter'));
	}

	function showStep(index) {
		if (index < 0 || index >= steps.length) {
			return;
		}

		steps.forEach(function (step, i) {
			step.classList.toggle('is-active', i === index);
		});

		currentStep = index;
		updateQuestionNextButton(steps[index]);
		resetAnswerPagination(steps[index]);
		applyDynamicStepPresentation(steps[index]);
		if (steps[index].dataset.key === 'verwendungsbereich') {
			const selected = state.verwendungsbereich || '';
			if (selected === 'buero' || selected === 'grosse_raeume') {
				showUsageStage(steps[index], 'arbeit');
			} else if (state.verwendungsbereich_main === 'alltag') {
				showUsageStage(steps[index], 'alltag');
			} else if (state.verwendungsbereich_main === 'formell') {
				showUsageStage(steps[index], 'formell');
			} else {
				resetUsageFlow(steps[index]);
			}
			updateUsageCrumbs(steps[index]);
		}
		syncSelectedUI(steps[index]);
		updateProgress();
		updateSelectedAnswers();
		resetInnerScroll();
		scrollAppTop();
		playQuestionAudioForStep(steps[index]);

		if (steps[index].dataset.type === 'result') {
			requestResults();
		}
	}

	function requestResults() {
		if (!resultsContainer || typeof syDuftberaterData === 'undefined') {
			return;
		}

		const loadingText = (syDuftberaterData.ui && syDuftberaterData.ui[currentLang] && syDuftberaterData.ui[currentLang].loading_text) ? syDuftberaterData.ui[currentLang].loading_text : 'Wir berechnen gerade passende Düfte für deine Auswahl …';
		resultsContainer.innerHTML = '<div class="sy-duftberater-loading">' + loadingText + '</div>';

		const formData = new FormData();
		formData.append('action', 'sy_duftberater_match');
		formData.append('nonce', syDuftberaterData.nonce);
		formData.append('lang', currentLang);

		Object.keys(state).forEach(function (key) {
			const value = state[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) {
					formData.append(key + '[]', item);
				});
			} else if (value !== null && value !== undefined) {
				formData.append(key, value);
			}
		});

		sydbAjaxRequest(formData, true)
			.then(function (data) {
				if (data && data.success && data.data && data.data.html) {
					resultsContainer.innerHTML = data.data.html;
				} else {
					resultsContainer.innerHTML = '<div class="sy-duftberater-empty">' + getUi('ajax_error_text', currentLang === 'ar' ? 'تعذر تحميل النتائج حالياً.' : (currentLang === 'en' ? 'The results could not be loaded right now.' : 'Die Ergebnisse konnten gerade nicht geladen werden.')) + '</div>';
				}
			})
			.catch(function () {
				resultsContainer.innerHTML = '<div class="sy-duftberater-empty">' + getUi('ajax_exception_text', currentLang === 'ar' ? 'حدث خطأ أثناء تحميل النتائج.' : (currentLang === 'en' ? 'An error occurred while loading the results.' : 'Beim Laden der Ergebnisse ist ein Fehler aufgetreten.')) + '</div>';
			});
	}


	function getPdfDownloadFilename(url) {
		try {
			const clean = String(url || '').split('?')[0].split('#')[0];
			const part = clean.split('/').pop() || '';
			return part && part.toLowerCase().indexOf('.pdf') !== -1 ? decodeURIComponent(part) : 'duftberater.pdf';
		} catch (e) {
			return 'duftberater.pdf';
		}
	}

	function triggerPdfDownload(url) {
		if (!url) return;
		const filename = getPdfDownloadFilename(url);
		const clickAnchor = function(href) {
			const a = document.createElement('a');
			a.href = href;
			a.download = filename;
			a.style.display = 'none';
			document.body.appendChild(a);
			a.click();
			setTimeout(function(){
				if (a && a.parentNode) { a.parentNode.removeChild(a); }
			}, 1200);
		};
		try {
			if (window.fetch && window.URL && window.URL.createObjectURL) {
				fetch(url, { credentials: 'same-origin' })
					.then(function(response){
						if (!response.ok) { throw new Error('download failed'); }
						return response.blob();
					})
					.then(function(blob){
						const objectUrl = window.URL.createObjectURL(blob);
						clickAnchor(objectUrl);
						setTimeout(function(){ window.URL.revokeObjectURL(objectUrl); }, 3000);
					})
					.catch(function(){ clickAnchor(url); });
			} else {
				clickAnchor(url);
			}
		} catch (e) {
			try { clickAnchor(url); } catch (ignore) {}
		}
	}


	function requestLeadPdf(form) {
		if (!form || typeof syDuftberaterData === 'undefined') {
			return;
		}
		const box = form.closest('[data-lead-box]');
		const msg = box ? box.querySelector('[data-lead-message]') : null;
		const submit = form.querySelector('button[type="submit"]');
		const name = (form.querySelector('[name="lead_name"]') || {}).value || '';
		const email = (form.querySelector('[name="lead_email"]') || {}).value || '';

		if (!name.trim() || !email.trim()) {
			if (msg) { msg.className = 'sy-duftberater-lead-message is-error'; msg.textContent = getUi('lead_error', 'Bitte Name und gültige E-Mail eingeben.'); }
			return;
		}

		const formData = new FormData();
		formData.append('action', 'sy_duftberater_lead_pdf');
		formData.append('nonce', syDuftberaterData.nonce);
		formData.append('lang', currentLang);
		formData.append('lead_name', name);
		formData.append('lead_email', email);

		Object.keys(state).forEach(function (key) {
			const value = state[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { formData.append(key + '[]', item); });
			} else if (value !== null && value !== undefined) {
				formData.append(key, value);
			}
		});

		if (submit) { submit.disabled = true; submit.classList.add('is-loading'); }
		if (msg) { msg.className = 'sy-duftberater-lead-message'; msg.textContent = getUi('lead_sending_text', getUi('loading_text', 'PDF wird erstellt …')); }

		sydbAjaxRequest(formData, true)
			.then(function (data) {
				if (submit) { submit.disabled = false; submit.classList.remove('is-loading'); }
				if (!msg) return;
				if (data && data.success && data.data) {
					msg.className = 'sy-duftberater-lead-message is-success';
					msg.innerHTML = '';
					const text = document.createElement('span');
					text.textContent = data.data.message || getUi('lead_success', 'Vielen Dank! Deine PDF wurde erstellt.');
					msg.appendChild(text);
					if (data.data.pdfUrl) {
						const pdfUrl = data.data.pdfUrl;
						const link = document.createElement('a');
						link.href = pdfUrl;
						link.target = '_blank';
						link.rel = 'noopener';
						link.setAttribute('download', getPdfDownloadFilename(pdfUrl));
						link.textContent = data.data.pdfText || getUi('lead_pdf_link', 'PDF öffnen');
						link.addEventListener('click', function(event){
							event.preventDefault();
							triggerPdfDownload(pdfUrl);
						});
						msg.appendChild(document.createTextNode(' '));
						msg.appendChild(link);
						if (sydbAutoDownloadEnabled) {
							triggerPdfDownload(pdfUrl);
						}
					}
					showCompletionPopup();
				} else {
					msg.className = 'sy-duftberater-lead-message is-error';
					msg.textContent = (data && data.data && data.data.message) ? data.data.message : getUi('lead_error', 'Bitte Name und gültige E-Mail eingeben.');
				}
			})
			.catch(function () {
				if (submit) { submit.disabled = false; submit.classList.remove('is-loading'); }
				if (msg) { msg.className = 'sy-duftberater-lead-message is-error'; msg.textContent = getUi('ajax_exception_text', 'Beim Erstellen der PDF ist ein Fehler aufgetreten.'); }
			});
	}

	function bindLeadForms() {
		Array.from(app.querySelectorAll('[data-lead-form]')).forEach(function(form){
			form.addEventListener('submit', function(event){
				event.preventDefault();
				requestLeadPdf(form);
			});
		});
	}

	function updateAnswerPager(wrapper, pageIndex) {
		if (!wrapper) return;
		const pages = Array.from(wrapper.querySelectorAll('[data-answer-page]'));
		if (!pages.length) return;
		const total = pages.length;
		let index = parseInt(pageIndex, 10);
		if (isNaN(index)) index = 0;
		if (index < 0) index = 0;
		if (index >= total) index = total - 1;
		wrapper.dataset.currentPage = String(index);
		pages.forEach(function(page, i){ page.classList.toggle('is-active', i === index); });
		const current = wrapper.querySelector('[data-answer-page-current]');
		if (current) current.textContent = String(index + 1);
		const prev = wrapper.querySelector('[data-answer-page-prev]');
		const next = wrapper.querySelector('[data-answer-page-next]');
		if (prev) prev.disabled = index === 0;
		if (next) next.disabled = index >= total - 1;
	}

	function resetAnswerPagination(step) {
		if (!step) return;
		Array.from(step.querySelectorAll('[data-answer-pages]')).forEach(function(wrapper){
			updateAnswerPager(wrapper, 0);
		});
	}

	function bindAnswerPagination() {
		Array.from(app.querySelectorAll('[data-answer-pages]')).forEach(function(wrapper){
			updateAnswerPager(wrapper, parseInt(wrapper.dataset.currentPage || '0', 10));
			const prev = wrapper.querySelector('[data-answer-page-prev]');
			const next = wrapper.querySelector('[data-answer-page-next]');
			if (prev) {
				prev.addEventListener('click', function(){
					playClickSound();
					updateAnswerPager(wrapper, parseInt(wrapper.dataset.currentPage || '0', 10) - 1);
				});
			}
			if (next) {
				next.addEventListener('click', function(){
					playClickSound();
					updateAnswerPager(wrapper, parseInt(wrapper.dataset.currentPage || '0', 10) + 1);
				});
			}
		});
	}

	function bindStepActions() {
		steps.forEach(function (step) {
			const btnPrev = step.querySelector('[data-action="prev"]');
			const btnNext = step.querySelector('[data-action="next"]');
			const btnRestart = step.querySelector('[data-action="restart"]');

			if (btnPrev) {
				btnPrev.addEventListener('click', function () {
					playClickSound();
					prevStep();
				});
			}

			if (btnNext) {
				btnNext.addEventListener('click', function () {
					playClickSound();
					nextStep();
				});
			}

			if (btnRestart) {
				btnRestart.addEventListener('click', function () {
					playClickSound();
					resetAdvisorToStart();
				});
			}
		});
	}

	function bindOptionCards() {
		const questionSteps = steps.filter(function (step) {
			return step.dataset.type === 'question';
		});

		questionSteps.forEach(function (step) {
			const cards = Array.from(step.querySelectorAll('.sy-duftberater-option-card'));
			const multiple = isMultiStep(step);

			cards.forEach(function (card) {
				card.addEventListener('click', function () {
					playClickSound();
					if (!multiple) {
						Array.from(step.querySelectorAll('.sy-duftberater-option-card')).forEach(function(c){ c.classList.remove('is-selected'); c.setAttribute('aria-pressed','false'); });
						card.classList.add('is-selected');
						card.setAttribute('aria-pressed','true');
					}
					if (step.dataset.key === 'verwendungsbereich') {
						handleUsageCardClick(step, card);
						return;
					}

					const value = card.dataset.value;
					let currentValue = getStepValue(step);

					if (multiple) {
						if (!Array.isArray(currentValue)) {
							currentValue = [];
						}

						if (currentValue.indexOf(value) !== -1) {
							currentValue = currentValue.filter(function (item) {
								return item !== value;
							});
						} else {
							currentValue = currentValue.concat(value);
						}

						setStepValue(step, currentValue);
						syncSelectedUI(step);
						applyDynamicStepPresentation(step);
						updateSelectedAnswers();
					} else {
						setStepValue(step, value);
						syncSelectedUI(step);
						updateSelectedAnswers();

						window.setTimeout(function () {
							nextStep();
						}, 170);
					}
				});
			});
		});
	}

	Array.from(app.querySelectorAll('[data-lang-select]')).forEach(function(btn){
		btn.addEventListener('click', function(){
			playClickSound();
			applyLanguage(btn.dataset.langSelect || 'de');
			showStep(1);
		});
	});

	if (btnStart) {
		btnStart.addEventListener('click', function () {
			playClickSound();
			showStep(2);
		});
	}

	applyLanguage(currentLang);
	bindStepActions();
	bindAnswerPagination();
	bindOptionCards();
	bindLeadForms();
	steps.forEach(function (step) {
		applyDynamicStepPresentation(step);
	});
	updateSelectedAnswers();
	showStep(0);
});
