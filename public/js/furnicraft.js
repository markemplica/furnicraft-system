document.addEventListener('DOMContentLoaded', () => {
    const roomPhotoInput = document.querySelector('[data-room-photo-input]');
    if (roomPhotoInput) {
        const uploadForm = roomPhotoInput.closest('form');
        const qualityMessage = uploadForm?.querySelector('[data-room-photo-quality]');
        const uploadButton = uploadForm?.querySelector('button[type="submit"]');
        let photoAccepted = false;
        let qualityRejected = false;
        const showRetake = (message) => {
            if (!qualityMessage) return;
            qualityMessage.replaceChildren(document.createTextNode(message + ' '));
            const retry = document.createElement('button');
            retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-secondary ms-2'; retry.textContent = 'Retake';
            retry.addEventListener('click', () => { roomPhotoInput.value = ''; photoAccepted = false; qualityRejected = false; qualityMessage.textContent = ''; roomPhotoInput.click(); });
            qualityMessage.append(retry);
            qualityRejected = true;
        };
        roomPhotoInput.addEventListener('change', async () => {
            photoAccepted = false; qualityRejected = false;
            const file = roomPhotoInput.files?.[0];
            if (!file) return;
            if (file.size > 8 * 1024 * 1024) { showRetake('This photo is over 8 MB. Choose a smaller image.'); return; }
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                if (Math.max(bitmap.width, bitmap.height) < 600) { bitmap.close(); showRetake('This photo is too small. Choose one at least 600 px on its longest side.'); return; }
                const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                const context = canvas.getContext('2d', { willReadFrequently: true });
                context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                bitmap.close();
                const sample = document.createElement('canvas');
                sample.width = 160; sample.height = Math.max(1, Math.round(160 * canvas.height / canvas.width));
                const sampleContext = sample.getContext('2d', { willReadFrequently: true });
                sampleContext.drawImage(canvas, 0, 0, sample.width, sample.height);
                const pixels = sampleContext.getImageData(0, 0, sample.width, sample.height).data;
                const gray = new Float32Array(sample.width * sample.height);
                let luminance = 0;
                for (let i = 0, p = 0; i < pixels.length; i += 4, p += 1) {
                    gray[p] = .2126 * pixels[i] + .7152 * pixels[i + 1] + .0722 * pixels[i + 2];
                    luminance += gray[p];
                }
                luminance /= gray.length;
                let sum = 0, sumSquares = 0, count = 0;
                for (let y = 1; y < sample.height - 1; y += 1) for (let x = 1; x < sample.width - 1; x += 1) {
                    const i = y * sample.width + x;
                    const laplacian = gray[i - 1] + gray[i + 1] + gray[i - sample.width] + gray[i + sample.width] - 4 * gray[i];
                    sum += laplacian; sumSquares += laplacian * laplacian; count += 1;
                }
                const variance = count ? sumSquares / count - (sum / count) ** 2 : 0;
                if (luminance < 42) { showRetake('This photo is too dark to analyze. Retake it in brighter light.'); return; }
                if (luminance > 242) { showRetake('This photo is too bright to analyze. Retake it away from direct glare.'); return; }
                if (variance < 24) { showRetake('This photo looks blurry. Hold the camera steady and retake it.'); return; }
                const processed = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', .88));
                if (!processed) { showRetake('This photo could not be prepared. Please retake it.'); return; }
                const transfer = new DataTransfer();
                transfer.items.add(new File([processed], 'room-photo.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
                roomPhotoInput.files = transfer.files;
                photoAccepted = true;
                if (qualityMessage) qualityMessage.textContent = 'Photo checked and prepared for automatic room fitting.';
            } catch (error) {
                showRetake('This photo could not be checked. Please choose another photo.');
            }
        });
        uploadForm?.addEventListener('submit', (event) => {
            if (!photoAccepted || qualityRejected) { event.preventDefault(); if (!roomPhotoInput.files?.length) roomPhotoInput.click(); return; }
            if (uploadButton) { uploadButton.disabled = true; uploadButton.textContent = 'Uploading · Analyzing room…'; }
        });
    }
    const preferencesForm = document.querySelector('[data-preferences-form]');

    if (preferencesForm) {
        const submitButton = preferencesForm.querySelector('[data-preferences-submit]');
        const status = preferencesForm.querySelector('[data-preferences-status]');
        const defaultButtonText = submitButton?.textContent ?? 'Update preferences';
        const snapshot = () => JSON.stringify(
            [...new FormData(preferencesForm).entries()]
                .filter(([name]) => name !== '_token')
                .map(([name, value]) => [name, String(value)])
        );
        let savedSnapshot = snapshot();
        let savedInPlace = false;

        const syncSubmitState = (force = false) => {
            if (!submitButton || (!force && submitButton.textContent === 'Saving…')) return;
            const changed = snapshot() !== savedSnapshot;
            submitButton.textContent = savedInPlace && !changed ? 'Updated successfully' : (savedInPlace ? 'Update preferences' : defaultButtonText);
            submitButton.disabled = savedInPlace && !changed;
        };

        const clearErrors = () => {
            preferencesForm.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
            preferencesForm.querySelectorAll('[data-async-error]').forEach((message) => message.remove());
        };

        const showStatus = (message, successful) => {
            if (!status) return;
            status.hidden = false;
            status.textContent = message;
            status.classList.toggle('alert-success', successful);
            status.classList.toggle('alert-danger', !successful);
        };

        preferencesForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            if (status) status.hidden = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Saving…';
            }

            try {
                const response = await fetch(preferencesForm.action, {
                    method: preferencesForm.method,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(preferencesForm),
                });
                const payload = await response.json();

                if (!response.ok) {
                    const errors = payload.errors ?? {};
                    let firstInvalid = null;
                    Object.entries(errors).forEach(([name, messages]) => {
                        const field = preferencesForm.elements.namedItem(name);
                        if (!(field instanceof HTMLElement)) return;
                        field.classList.add('is-invalid');
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback d-block';
                        feedback.dataset.asyncError = '';
                        feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
                        field.insertAdjacentElement('afterend', feedback);
                        firstInvalid ??= field;
                    });
                    showStatus(payload.message ?? 'Please check the highlighted preferences.', false);
                    firstInvalid?.focus();
                    return;
                }

                const furnitureType = payload.preference?.furniture_type ?? preferencesForm.elements.namedItem('furniture_type')?.value ?? 'furniture';
                if (status) status.hidden = true;

                const savedState = document.querySelector('[data-preference-save-state]');
                if (savedState) savedState.hidden = false;
                document.querySelector('[data-step-one-warning]')?.setAttribute('hidden', '');
                const roomFieldset = document.querySelector('[data-room-upload-fieldset]');
                if (roomFieldset) roomFieldset.disabled = false;
                document.querySelectorAll('[data-saved-furniture-type]').forEach((element) => element.textContent = furnitureType);
                document.querySelectorAll('[data-saved-furniture-type-lower]').forEach((element) => element.textContent = furnitureType.toLowerCase());

                const stepOne = document.querySelector('[data-workflow-step="1"]');
                const stepTwo = document.querySelector('[data-workflow-step="2"]');
                stepOne?.classList.remove('is-current');
                stepOne?.classList.add('is-complete');
                stepOne?.removeAttribute('aria-current');
                stepTwo?.classList.add('is-current');
                stepTwo?.setAttribute('aria-current', 'step');

                const roomPicker = document.querySelector('#room_image_id');
                const generateButton = document.querySelector('[data-generate-recommendations]');
                if (generateButton) generateButton.disabled = !roomPicker?.value;
                savedSnapshot = snapshot();
                savedInPlace = true;
            } catch (error) {
                showStatus('Preferences could not be saved right now. Please try again.', false);
            } finally {
                if (submitButton) {
                    if (!savedInPlace) {
                        submitButton.disabled = false;
                        if (submitButton.textContent === 'Saving…') submitButton.textContent = defaultButtonText;
                    } else {
                        syncSubmitState(true);
                    }
                }
            }
        });

        preferencesForm.addEventListener('input', syncSubmitState);
        preferencesForm.addEventListener('change', syncSubmitState);
    }

    const input = document.querySelector('[data-live-search]');

    if (input) {
        const box = document.querySelector('[data-search-results]');
        const grid = document.querySelector('[data-product-grid]');
        const count = document.querySelector('[data-product-count]');
        const pagination = document.querySelector('[data-catalog-pagination]');
        const liveEmpty = document.querySelector('[data-live-search-empty]');
        const serverEmpty = document.querySelector('[data-server-empty]');
        const originalGrid = grid?.innerHTML ?? '';
        const originalCount = count?.textContent ?? '';
        const originallyEmpty = grid?.hidden ?? true;
        let timer;
        let requestNumber = 0;

        const restoreCatalog = () => {
            if (grid) {
                grid.innerHTML = originalGrid;
                grid.hidden = originallyEmpty;
            }
            if (count) count.textContent = originalCount;
            if (pagination) pagination.hidden = false;
            if (liveEmpty) liveEmpty.hidden = true;
            if (serverEmpty) serverEmpty.hidden = !originallyEmpty;
            box.innerHTML = '';
            box.hidden = true;
        };

        const renderProduct = (item) => `
            <article class="product-card">
                <a href="${escapeHtml(item.url)}"><img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}"></a>
                <div class="product-card-body">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="product-meta">${escapeHtml(item.category)}</span>
                        ${item.saved ? '<span class="status status-confirmed">Saved</span>' : ''}
                    </div>
                    <h3 class="mt-2"><a class="text-reset text-decoration-none" href="${escapeHtml(item.url)}">${escapeHtml(item.name)}</a></h3>
                    <p class="product-meta"><span class="swatch ${escapeHtml(String(item.wood_type).toLowerCase())}"></span>${escapeHtml(item.wood_type)} · ${escapeHtml(item.varnish_finish)} · ${escapeHtml(item.style)}</p>
                    <p class="product-price">PHP ${Number(item.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                </div>
            </article>`;

        const showMatches = (items, query) => {
            if (grid) {
                grid.innerHTML = items.map(renderProduct).join('');
                grid.hidden = items.length === 0;
            }
            if (count) count.textContent = `${items.length} ${items.length === 1 ? 'match' : 'matches'} for “${query}”`;
            if (pagination) pagination.hidden = true;
            if (serverEmpty) serverEmpty.hidden = true;
            if (liveEmpty) liveEmpty.hidden = items.length > 0;
        };

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const query = input.value.trim();

            if (query.length < 2) {
                requestNumber += 1;
                restoreCatalog();
                return;
            }

            timer = setTimeout(async () => {
                const currentRequest = ++requestNumber;
                try {
                    const response = await fetch(`${input.dataset.endpoint}?q=${encodeURIComponent(query)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const items = await response.json();
                    if (currentRequest !== requestNumber) return;

                    box.innerHTML = items.length
                        ? items.slice(0, 6).map((item) => `<a class="search-result" href="${escapeHtml(item.url)}"><img src="${escapeHtml(item.image)}" alt=""><span><strong>${escapeHtml(item.name)}</strong><small class="d-block text-muted">${escapeHtml(item.category)}</small></span><span>PHP ${Number(item.price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></a>`).join('')
                        : `<div class="p-3 text-muted">No furniture matches “${escapeHtml(query)}” yet. Try a different term.</div>`;
                    box.hidden = false;
                    showMatches(items, query);
                } catch (error) {
                    box.innerHTML = '<div class="p-3 text-muted">Search is temporarily unavailable. Use the filters below.</div>';
                    box.hidden = false;
                }
            }, 280);
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.search-wrap')) box.hidden = true;
        });
    }

    const roomOptions = [...document.querySelectorAll('[data-room-option]')];
    const roomPicker = roomOptions.length ? document.querySelector('#room_image_id') : null;
    const roomSelectionStatus = document.querySelector('[data-room-selection-status]');
    const roomStepState = document.querySelector('[data-room-step-state]');
    const selectedRoomLabel = document.querySelector('[data-selected-room-label]');
    const generateRecommendations = document.querySelector('[data-generate-recommendations]');

    if (roomOptions.length && roomPicker) {
        const selectRoom = (selectedOption, announce = true) => {
            roomOptions.forEach((option) => {
                const selected = option === selectedOption;
                option.classList.toggle('is-selected', selected);
                option.setAttribute('aria-pressed', String(selected));
                const badge = option.querySelector('[data-room-selected-badge]');
                if (badge) badge.hidden = !selected;
            });

            roomPicker.value = selectedOption.dataset.roomId;
            const roomReady = selectedOption.dataset.roomReady === 'true';
            if (roomStepState) roomStepState.hidden = false;
            if (generateRecommendations) generateRecommendations.disabled = !roomReady;
            if (selectedRoomLabel) {
                selectedRoomLabel.textContent = roomReady
                    ? `Room ${selectedOption.dataset.roomId} selected. Only products matching your saved furniture type and preferences will be ranked.`
                    : `Room ${selectedOption.dataset.roomId} is still being analyzed. Recommendations will unlock automatically when it is ready.`;
            }
            if (announce && roomSelectionStatus) {
                roomSelectionStatus.textContent = `Room ${selectedOption.dataset.roomId} selected. You can generate recommendations below without reloading the page.`;
            }
        };

        roomOptions.forEach((option) => {
            option.addEventListener('click', (event) => {
                if (event.target.closest('a, button, form')) return;
                selectRoom(option);
            });
            option.addEventListener('keydown', (event) => {
                if (event.target.closest('a, button, form')) return;
                if (!['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                selectRoom(option);
            });
        });

        roomPicker.addEventListener('change', () => {
            const option = roomOptions.find((item) => item.dataset.roomId === roomPicker.value);
            if (option) selectRoom(option, false);
        });

        const initialRoom = roomOptions.find((option) => option.dataset.roomId === roomPicker.value);
        if (initialRoom) selectRoom(initialRoom, false);
    }

    const analysisWatches = [...document.querySelectorAll('[data-room-analysis-watch]')];
    const activeAnalysisStatuses = ['queued', 'processing', 'retrying'];
    if (analysisWatches.some((element) => activeAnalysisStatuses.includes(element.dataset.roomAnalysisCurrent || (element.dataset.roomReady === 'true' ? 'completed' : 'queued')))) {
        const labels = { queued: 'Room analysis queued', processing: 'Analyzing room image', retrying: 'AI temporarily unavailable · retrying', completed: 'AI analyzed', fallback: 'Local guidance', failed: 'Needs retry' };
        const poll = async () => {
            await Promise.all(analysisWatches.map(async (element) => {
                const current = element.dataset.roomAnalysisCurrent || (element.dataset.roomReady === 'true' ? 'completed' : 'queued');
                if (!activeAnalysisStatuses.includes(current)) return;
                try {
                    const response = await fetch(element.dataset.roomAnalysisWatch, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const payload = await response.json();
                    element.dataset.roomAnalysisCurrent = payload.status;
                    element.querySelector('[data-room-analysis-label]')?.replaceChildren(document.createTextNode(labels[payload.status] || payload.status));
                    const message = element.querySelector('[data-room-analysis-message]');
                    if (message) message.textContent = payload.message || (payload.ready ? 'Room analysis is ready.' : '');
                    if (payload.ready && payload.analysis) {
                        document.querySelectorAll('[data-preview-analysis]').forEach((node) => { node.textContent = JSON.stringify(payload.analysis); });
                        document.querySelectorAll('[data-room-preview]').forEach((frame) => {
                            const previous = frame.roomPreviewEngine;
                            if (previous?.canvas) previous.canvas.width = 0;
                            const engine = window.RoomPreviewEngine ? new window.RoomPreviewEngine(frame) : null;
                            if (engine) { frame.roomPreviewEngine = engine; engine.start(); }
                        });
                    }
                } catch (error) {
                    // Keep the current state; the next poll can recover from a transient network error.
                }
            }));
        };
        window.setInterval(poll, 3000);
    }
    const previewWatches = [...document.querySelectorAll('[data-preview-watch]')];
    const activePreviewStatuses = ['queued', 'processing', 'retrying'];
    if (previewWatches.some((element) => activePreviewStatuses.includes(element.dataset.previewStatus))) {
        const pollPreviews = async () => {
            await Promise.all(previewWatches.map(async (element) => {
                if (!activePreviewStatuses.includes(element.dataset.previewStatus)) return;
                try {
                    const response = await fetch(element.dataset.previewWatch, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const payload = await response.json();
                    element.dataset.previewStatus = payload.status;
                    const message = element.querySelector('[data-preview-message]');
                    const label = element.querySelector('[data-preview-label]');
                    if (message && payload.message) message.textContent = payload.message;
                    if (label && ['failed', 'fallback', 'cancelled'].includes(payload.status)) label.textContent = 'Showing local 2D placement preview';
                    if (payload.status === 'completed' && message) message.textContent = payload.message || 'The final automatic preview is ready.';
                } catch (error) {
                    // Keep polling after transient network errors.
                }
            }));
        };
        window.setInterval(pollPreviews, 3000);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
});
