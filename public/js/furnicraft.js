document.addEventListener('DOMContentLoaded', () => {
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
            let shouldReload = false;
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
                    if (message && payload.message) message.textContent = ` · ${payload.message}`;
                    if (!activeAnalysisStatuses.includes(payload.status)) shouldReload = true;
                } catch (error) {
                    // Keep the current state; the next poll can recover from a transient network error.
                }
            }));
            if (shouldReload) window.location.reload();
        };
        window.setInterval(poll, 3000);
    }

    const previewWatches = [...document.querySelectorAll('[data-preview-watch]')];
    const activePreviewStatuses = ['queued', 'processing', 'retrying'];
    if (previewWatches.some((element) => activePreviewStatuses.includes(element.dataset.previewStatus))) {
        const pollPreviews = async () => {
            let shouldReload = false;
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
                    if (payload.status === 'completed') shouldReload = true;
                } catch (error) {
                    // Keep polling after transient network errors.
                }
            }));
            if (shouldReload) window.location.reload();
        };
        window.setInterval(pollPreviews, 3000);
    }

    const comparison = document.querySelector('[data-room-comparison]');

    if (comparison) {
        const product = document.querySelector('[data-comparison-product]');
        const status = document.querySelector('[data-placement-status]');
        const dimensions = document.querySelector('[data-placement-dimensions]');
        const guidance = document.querySelector('[data-placement-guidance]');
        const confirmPlacement = document.querySelector('[data-placement-confirm]');
        const roomBaseImage = comparison.querySelector('.comparison-room-base');
        const productWarp = comparison.querySelector('[data-comparison-product-warp]');
        const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));
        const parseQuad = (value) => {
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;
                if (!Array.isArray(parsed) || parsed.length !== 4) return null;
                const points = parsed.map((point) => Array.isArray(point) && point.length >= 2
                    ? [Number(point[0]), Number(point[1])]
                    : null);
                return points.every((point) => point && point.every(Number.isFinite)) ? points : null;
            } catch (error) {
                return null;
            }
        };
        const initialQuad = parseQuad(document.querySelector('[data-initial-placement-quad]')?.textContent || 'null');
        const placement = {
            x: 50,
            y: 84,
            baseWidth: 40,
            scale: 1,
            rotation: 0,
            perspective: 0,
            flip: 1,
            blend: true,
            quad: initialQuad,
        };
        let awaitingPlacementPoint = false;
        let needsPlacementConfirmation = false;
        let placementRenderable = true;
        let productCrop = null;

        const setComparison = (value) => {
            const split = clamp(Number(value), 0, 100);
            comparison.style.setProperty('--split', split + '%');
            comparison.setAttribute('aria-valuenow', String(Math.round(split)));
        };

        const mapQuadPoint = (quad, u, v) => {
            const [topLeft, topRight, bottomRight, bottomLeft] = quad;
            return [
                ((1 - u) * (1 - v) * topLeft[0]) + (u * (1 - v) * topRight[0]) + (u * v * bottomRight[0]) + ((1 - u) * v * bottomLeft[0]),
                ((1 - u) * (1 - v) * topLeft[1]) + (u * (1 - v) * topRight[1]) + (u * v * bottomRight[1]) + ((1 - u) * v * bottomLeft[1]),
            ];
        };

        const drawTriangle = (context, image, source, destination) => {
            const [[sx0, sy0], [sx1, sy1], [sx2, sy2]] = source;
            const [[dx0, dy0], [dx1, dy1], [dx2, dy2]] = destination;
            const denominator = (sx0 * (sy1 - sy2)) + (sx1 * (sy2 - sy0)) + (sx2 * (sy0 - sy1));
            if (Math.abs(denominator) < 0.0001) return;

            const a = ((dx0 * (sy1 - sy2)) + (dx1 * (sy2 - sy0)) + (dx2 * (sy0 - sy1))) / denominator;
            const b = ((dy0 * (sy1 - sy2)) + (dy1 * (sy2 - sy0)) + (dy2 * (sy0 - sy1))) / denominator;
            const c = ((dx0 * (sx2 - sx1)) + (dx1 * (sx0 - sx2)) + (dx2 * (sx1 - sx0))) / denominator;
            const d = ((dy0 * (sx2 - sx1)) + (dy1 * (sx0 - sx2)) + (dy2 * (sx1 - sx0))) / denominator;
            const e = ((dx0 * ((sx1 * sy2) - (sx2 * sy1))) + (dx1 * ((sx2 * sy0) - (sx0 * sy2))) + (dx2 * ((sx0 * sy1) - (sx1 * sy0)))) / denominator;
            const f = ((dy0 * ((sx1 * sy2) - (sx2 * sy1))) + (dy1 * ((sx2 * sy0) - (sx0 * sy2))) + (dy2 * ((sx0 * sy1) - (sx1 * sy0)))) / denominator;

            context.save();
            context.beginPath();
            context.moveTo(dx0, dy0);
            context.lineTo(dx1, dy1);
            context.lineTo(dx2, dy2);
            context.closePath();
            context.clip();
            context.transform(a, b, c, d, e, f);
            context.drawImage(image, 0, 0);
            context.restore();
        };

        const opaqueBounds = (image) => {
            if (productCrop) return productCrop;
            const sample = document.createElement('canvas');
            const scale = Math.min(1, 320 / Math.max(image.naturalWidth, image.naturalHeight));
            sample.width = Math.max(1, Math.round(image.naturalWidth * scale));
            sample.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const sampleContext = sample.getContext('2d', { willReadFrequently: true });
            if (!sampleContext) return { x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight };
            sampleContext.drawImage(image, 0, 0, sample.width, sample.height);

            try {
                const pixels = sampleContext.getImageData(0, 0, sample.width, sample.height).data;
                let minimumX = sample.width;
                let minimumY = sample.height;
                let maximumX = 0;
                let maximumY = 0;
                let found = false;
                for (let y = 0; y < sample.height; y += 1) {
                    for (let x = 0; x < sample.width; x += 1) {
                        if (pixels[((y * sample.width) + x) * 4 + 3] < 12) continue;
                        found = true;
                        minimumX = Math.min(minimumX, x);
                        minimumY = Math.min(minimumY, y);
                        maximumX = Math.max(maximumX, x);
                        maximumY = Math.max(maximumY, y);
                    }
                }
                productCrop = found
                    ? {
                        x: minimumX / scale,
                        y: minimumY / scale,
                        width: Math.max(1, (maximumX - minimumX + 1) / scale),
                        height: Math.max(1, (maximumY - minimumY + 1) / scale),
                    }
                    : { x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight };
            } catch (error) {
                productCrop = { x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight };
            }

            return productCrop;
        };

        const drawPerspectiveWarp = () => {
            if (!productWarp || !product || !placement.quad || !product.complete || !product.naturalWidth || !roomBaseImage?.naturalWidth) return false;

            const width = roomBaseImage.naturalWidth;
            const height = roomBaseImage.naturalHeight;
            if (productWarp.width !== width || productWarp.height !== height) {
                productWarp.width = width;
                productWarp.height = height;
            }
            const context = productWarp.getContext('2d');
            if (!context) return false;
            context.clearRect(0, 0, width, height);
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';

            const quad = placement.quad.map(([x, y]) => [(x / 100) * width, (y / 100) * height]);
            const crop = opaqueBounds(product);
            const columns = 12;
            const rows = 20;
            for (let row = 0; row < rows; row += 1) {
                for (let column = 0; column < columns; column += 1) {
                    const u0 = column / columns;
                    const u1 = (column + 1) / columns;
                    const v0 = row / rows;
                    const v1 = (row + 1) / rows;
                    const sourceTopLeft = [crop.x + (u0 * crop.width), crop.y + (v0 * crop.height)];
                    const sourceTopRight = [crop.x + (u1 * crop.width), crop.y + (v0 * crop.height)];
                    const sourceBottomRight = [crop.x + (u1 * crop.width), crop.y + (v1 * crop.height)];
                    const sourceBottomLeft = [crop.x + (u0 * crop.width), crop.y + (v1 * crop.height)];
                    const destinationTopLeft = mapQuadPoint(quad, u0, v0);
                    const destinationTopRight = mapQuadPoint(quad, u1, v0);
                    const destinationBottomRight = mapQuadPoint(quad, u1, v1);
                    const destinationBottomLeft = mapQuadPoint(quad, u0, v1);

                    drawTriangle(context, product, [sourceTopLeft, sourceTopRight, sourceBottomRight], [destinationTopLeft, destinationTopRight, destinationBottomRight]);
                    drawTriangle(context, product, [sourceTopLeft, sourceBottomRight, sourceBottomLeft], [destinationTopLeft, destinationBottomRight, destinationBottomLeft]);
                }
            }

            return true;
        };

        const renderPlacement = () => {
            if (!placementRenderable) {
                if (product) product.hidden = true;
                if (productWarp) {
                    productWarp.hidden = true;
                    productWarp.getContext('2d')?.clearRect(0, 0, productWarp.width, productWarp.height);
                }
                return;
            }
            comparison.style.setProperty('--product-x', placement.x + '%');
            comparison.style.setProperty('--product-y', placement.y + '%');
            comparison.style.setProperty('--product-width', clamp(placement.baseWidth * placement.scale * (0.55 + (placement.y / 100) * 0.55), 8, 92) + '%');
            comparison.style.setProperty('--product-rotation', placement.rotation + 'deg');
            comparison.style.setProperty('--product-perspective', placement.perspective + 'deg');
            comparison.style.setProperty('--product-flip', placement.flip);
            product?.classList.toggle('is-blended', placement.blend);
            productWarp?.classList.toggle('is-blended', placement.blend);
            const warped = drawPerspectiveWarp();
            if (product) product.hidden = warped;
            if (productWarp) productWarp.hidden = !warped;
        };

        const describeFit = () => {
            const furnitureWidth = Number(comparison.dataset.productWidth || 0);
            const furnitureDepth = Number(comparison.dataset.productDepth || 0);
            const furnitureHeight = Number(comparison.dataset.productHeight || 0);
            const roomWidth = Number(comparison.dataset.roomWidth || 0);
            const roomDepth = Number(comparison.dataset.roomDepth || 0);
            const measuredRoom = roomWidth > 0;
            const analyzedType = String(comparison.dataset.placementType || '').toLowerCase();
            const productType = String(comparison.dataset.productType || '').toLowerCase();
            const aiGuided = comparison.dataset.placementAi === 'true' && (!analyzedType || analyzedType === productType);
            const generatedResult = comparison.dataset.generatedResult === 'true';
            const geminiUnavailable = comparison.dataset.geminiUnavailable === 'true';
            const detectedX = Number(comparison.dataset.placementX || 50);
            const detectedY = Number(comparison.dataset.placementY || 86);
            const detectedWidth = Number(comparison.dataset.placementWidth || 72);
            const detectedRotation = Number(comparison.dataset.placementRotation || 0);
            const detectedPerspective = Number(comparison.dataset.placementPerspective || 0);
            const detectedFlip = Number(comparison.dataset.placementFlip || 1);
            const confidence = String(comparison.dataset.placementConfidence || 'low');
            const placementSource = String(comparison.dataset.placementSource || '');
            const placementReason = String(comparison.dataset.placementReason || '');
            const validationStatus = String(comparison.dataset.placementValidation || 'advisory');
            const measurementStatus = String(comparison.dataset.placementMeasurement || 'measurements-missing');
            const fitBasis = String(comparison.dataset.placementBasis || 'image-estimate');
            const hasPlacement = placementSource.length > 0;
            needsPlacementConfirmation = comparison.dataset.placementNeedsConfirmation === 'true';
            placementRenderable = comparison.dataset.placementRenderable !== 'false';

            const dimensionWidth = measuredRoom
                ? clamp((furnitureWidth / (roomWidth * 100)) * 100, 10, 82)
                : clamp(furnitureWidth / 3.5, 18, 62);
            const openingPlacement = productType.includes('door') && ['candidate-zone', 'scene-opening'].includes(placementSource);
            placement.baseWidth = openingPlacement
                ? clamp(detectedWidth, 10, 55)
                : (hasPlacement ? Math.min(dimensionWidth, clamp(detectedWidth, 15, 90)) : dimensionWidth);
            placement.y = hasPlacement
                ? clamp(detectedY, 30, 96)
                : (String(comparison.dataset.productCategory || '').toLowerCase().includes('door') ? 90 : 84);
            const perspectiveWidth = clamp(placement.baseWidth * (0.55 + (placement.y / 100) * 0.55), 8, 92);
            placement.x = hasPlacement
                ? clamp(detectedX, perspectiveWidth / 2, 100 - (perspectiveWidth / 2))
                : 50;
            placement.scale = 1;
            placement.rotation = clamp(detectedRotation, -18, 18);
            placement.perspective = clamp(detectedPerspective, -24, 24);
            placement.flip = detectedFlip === -1 ? -1 : 1;

            if (status) {
                status.textContent = !placementRenderable
                    ? validationStatus === 'does-not-fit'
                        ? 'Does not fit supplied room measurements'
                        : validationStatus === 'no-opening'
                            ? 'No reliable doorway detected'
                            : 'No validated placement available'
                    : generatedResult
                    ? 'Gemini furnished-room result'
                    : geminiUnavailable
                        ? 'Gemini image generation unavailable · showing the local 2D overlay'
                        : ['candidate-zone', 'scene-opening'].includes(placementSource)
                            ? validationStatus === 'supported'
                                ? `AI placement supported · ${confidence} confidence`
                                : `AI zone detected · verification required`
                            : needsPlacementConfirmation
                                ? 'Suggested position · confirmation recommended'
                                : aiGuided
                                    ? `AI-guided fit · ${confidence} confidence`
                                    : (measuredRoom ? 'Dimension-based fit' : 'Approximate fit');
            }
            if (dimensions) {
                const furnitureSize = [furnitureWidth, furnitureDepth, furnitureHeight]
                    .map((value) => value ? Number(value).toLocaleString('en-PH', { maximumFractionDigits: 1 }) : '?')
                    .join(' × ');
                const roomSize = roomWidth > 0
                    ? roomWidth.toLocaleString('en-PH', { maximumFractionDigits: 1 }) + ' × ' + (roomDepth || '?') + ' m room'
                    : 'Add room measurements for more accurate scale';
                const measurementLabel = measurementStatus === 'measured-fit'
                    ? 'catalog dimensions checked'
                    : measurementStatus === 'does-not-fit'
                        ? 'dimension conflict'
                        : measurementStatus === 'opening-measurements-required'
                            ? 'measure doorway before installation'
                            : 'measurements needed';
                dimensions.textContent = furnitureSize + ' cm furniture · ' + roomSize + ' · ' + measurementLabel;
            }
            if (guidance) {
                guidance.textContent = !placementRenderable
                    ? (placementReason || 'The product is hidden because no defensible placement is available.')
                    : needsPlacementConfirmation
                        ? `${placementReason || 'The placement has limited evidence.'} This remains a planning estimate; verify actual measurements.`
                        : `${placementReason || 'The furniture was placed in the best detected clear area.'} Basis: ${fitBasis.replaceAll('-', ' ')}.`;
            }
            if (confirmPlacement) confirmPlacement.hidden = !needsPlacementConfirmation || !placementRenderable;
            comparison.classList.toggle('needs-placement-confirmation', needsPlacementConfirmation);
            comparison.classList.toggle('placement-blocked', !placementRenderable);

            renderPlacement();
        };

        const syncRoomAspectRatio = () => {
            if (roomBaseImage?.naturalWidth && roomBaseImage?.naturalHeight) {
                comparison.style.aspectRatio = `${roomBaseImage.naturalWidth} / ${roomBaseImage.naturalHeight}`;
                renderPlacement();
            }
        };
        if (roomBaseImage?.complete) syncRoomAspectRatio();
        else roomBaseImage?.addEventListener('load', syncRoomAspectRatio, { once: true });
        product?.addEventListener('load', () => {
            productCrop = null;
            renderPlacement();
        });

        let sliding = false;
        const updateSlider = (event) => {
            const bounds = comparison.getBoundingClientRect();
            setComparison(((event.clientX - bounds.left) / bounds.width) * 100);
        };
        comparison.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) return;
            if (awaitingPlacementPoint) {
                const bounds = comparison.getBoundingClientRect();
                placement.x = clamp(((event.clientX - bounds.left) / bounds.width) * 100, 5, 95);
                placement.y = clamp(((event.clientY - bounds.top) / bounds.height) * 100, 30, 96);
                placement.quad = null;
                awaitingPlacementPoint = false;
                needsPlacementConfirmation = false;
                comparison.dataset.placementNeedsConfirmation = 'false';
                comparison.dataset.placementSource = 'customer-confirmed';
                comparison.classList.remove('is-choosing-placement', 'needs-placement-confirmation');
                if (confirmPlacement) confirmPlacement.hidden = true;
                if (status) status.textContent = 'Customer-selected planning position';
                if (guidance) guidance.textContent = 'Suggested position selected. This does not confirm physical fit; verify actual measurements.';
                renderPlacement();
                event.preventDefault();
                return;
            }
            sliding = true;
            comparison.setPointerCapture(event.pointerId);
            updateSlider(event);
        });
        comparison.addEventListener('pointermove', (event) => {
            if (sliding) updateSlider(event);
        });
        const stopSliding = (event) => {
            sliding = false;
            if (comparison.hasPointerCapture(event.pointerId)) comparison.releasePointerCapture(event.pointerId);
        };
        comparison.addEventListener('pointerup', stopSliding);
        comparison.addEventListener('pointercancel', stopSliding);
        comparison.addEventListener('keydown', (event) => {
            const current = Number(comparison.getAttribute('aria-valuenow') || 50);
            const movement = event.shiftKey ? 10 : 2;
            const next = event.key === 'ArrowLeft' ? current - movement
                : event.key === 'ArrowRight' ? current + movement
                    : event.key === 'Home' ? 0
                        : event.key === 'End' ? 100
                            : null;
            if (next === null) return;
            event.preventDefault();
            setComparison(next);
        });

        confirmPlacement?.addEventListener('click', () => {
            awaitingPlacementPoint = true;
            comparison.classList.add('is-choosing-placement');
            comparison.focus();
            if (guidance) guidance.textContent = 'Click once on a visible clear area for a planning position. Actual measurements are still required.';
        });

        const recommendationPreviewButtons = [...document.querySelectorAll('[data-recommendation-preview]')];
        const recommendationQuads = [...document.querySelectorAll('[data-recommendation-placement-quad]')];
        recommendationPreviewButtons.forEach((button, index) => {
            button.addEventListener('click', () => {
                recommendationPreviewButtons.forEach((item) => {
                    const selected = item === button;
                    item.classList.toggle('btn-primary', selected);
                    item.classList.toggle('btn-outline-primary', !selected);
                    item.classList.toggle('is-active', selected);
                    item.setAttribute('aria-pressed', String(selected));
                    item.textContent = selected ? 'Showing in room' : 'Preview here';
                });

                comparison.dataset.productWidth = button.dataset.width || '';
                comparison.dataset.productDepth = button.dataset.depth || '';
                comparison.dataset.productHeight = button.dataset.height || '';
                comparison.dataset.productCategory = button.dataset.category || '';
                comparison.dataset.productType = button.dataset.type || '';
                comparison.dataset.placementX = button.dataset.placementX || '50';
                comparison.dataset.placementY = button.dataset.placementY || '86';
                comparison.dataset.placementWidth = button.dataset.placementWidth || '72';
                comparison.dataset.placementRotation = button.dataset.placementRotation || '0';
                comparison.dataset.placementPerspective = button.dataset.placementPerspective || '0';
                comparison.dataset.placementFlip = button.dataset.placementFlip || '1';
                comparison.dataset.placementConfidence = button.dataset.placementConfidence || 'low';
                comparison.dataset.placementSource = button.dataset.placementSource || 'type-fallback';
                comparison.dataset.placementReason = button.dataset.placementReason || '';
                comparison.dataset.placementNeedsConfirmation = button.dataset.placementNeedsConfirmation || 'true';
                comparison.dataset.placementRenderable = button.dataset.placementRenderable || 'true';
                comparison.dataset.placementValidation = button.dataset.placementValidation || 'advisory';
                comparison.dataset.placementMeasurement = button.dataset.placementMeasurement || 'measurements-missing';
                comparison.dataset.placementBasis = button.dataset.placementBasis || 'image-estimate';
                comparison.dataset.placementAi = ['candidate-zone', 'scene-opening'].includes(comparison.dataset.placementSource) ? 'true' : 'false';
                placement.quad = parseQuad(recommendationQuads[index]?.textContent || 'null');
                if (product) {
                    productCrop = null;
                    product.src = button.dataset.image;
                }
                document.querySelectorAll('[data-inline-preview-name]').forEach((element) => element.textContent = button.dataset.name);
                document.querySelectorAll('[data-inline-preview-card-image]').forEach((element) => {
                    element.src = button.dataset.image;
                    element.alt = button.dataset.name;
                });
                document.querySelectorAll('[data-inline-preview-price]').forEach((element) => element.textContent = button.dataset.price);
                document.querySelectorAll('[data-inline-preview-score]').forEach((element) => element.textContent = button.dataset.score);
                document.querySelectorAll('[data-inline-preview-link]').forEach((element) => element.href = button.dataset.url);
                describeFit();
                setComparison(50);
                comparison.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        describeFit();

        setComparison(50);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
});
