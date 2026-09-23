document.addEventListener('DOMContentLoaded', () => {
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

    const comparison = document.querySelector('[data-room-comparison]');
    const range = document.querySelector('[data-compare-range]');
    const select = document.querySelector('[data-product-picker]');

    if (comparison && range) {
        const modeButtons = [...document.querySelectorAll('[data-compare-mode]')];
        const product = document.querySelector('[data-comparison-product]');
        const scaleInput = document.querySelector('[data-product-scale]');
        const rotationInput = document.querySelector('[data-product-rotation]');
        const scaleOutput = document.querySelector('[data-scale-output]');
        const rotationOutput = document.querySelector('[data-rotation-output]');
        const status = document.querySelector('[data-placement-status]');
        const dimensions = document.querySelector('[data-placement-dimensions]');
        const flipButton = document.querySelector('[data-flip-product]');
        const blendButton = document.querySelector('[data-blend-product]');
        const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));
        const placement = {
            x: 50,
            y: 84,
            baseWidth: 40,
            scale: 1,
            rotation: 0,
            flip: 1,
            blend: true,
            dragging: false,
        };

        const setComparison = (value, mode = '') => {
            const split = clamp(Number(value), 0, 100);
            comparison.style.setProperty('--split', split + '%');
            range.value = split;
            modeButtons.forEach((button) => {
                const active = button.dataset.compareMode === mode;
                button.setAttribute('aria-pressed', String(active));
                button.classList.toggle('btn-dark', active);
                button.classList.toggle('btn-outline-dark', !active);
            });
        };

        const renderPlacement = () => {
            comparison.style.setProperty('--product-x', placement.x + '%');
            comparison.style.setProperty('--product-y', placement.y + '%');
            comparison.style.setProperty('--product-width', clamp(placement.baseWidth * placement.scale * (0.55 + (placement.y / 100) * 0.55), 8, 92) + '%');
            comparison.style.setProperty('--product-rotation', placement.rotation + 'deg');
            comparison.style.setProperty('--product-flip', placement.flip);
            product?.classList.toggle('is-blended', placement.blend);
            if (scaleOutput) scaleOutput.textContent = Math.round(placement.scale * 100) + '%';
            if (rotationOutput) rotationOutput.textContent = Math.round(placement.rotation) + '°';
        };

        const describeFit = (option) => {
            const furnitureWidth = Number(option.dataset.width || 0);
            const furnitureDepth = Number(option.dataset.depth || 0);
            const furnitureHeight = Number(option.dataset.height || 0);
            const roomWidth = Number(comparison.dataset.roomWidth || 0);
            const roomDepth = Number(comparison.dataset.roomDepth || 0);
            const measuredRoom = roomWidth > 0;

            placement.baseWidth = measuredRoom
                ? clamp((furnitureWidth / (roomWidth * 100)) * 100, 10, 82)
                : clamp(furnitureWidth / 3.5, 18, 62);
            placement.x = 50;
            placement.y = String(option.dataset.category || '').toLowerCase().includes('door') ? 90 : 84;
            placement.scale = 1;
            placement.rotation = 0;
            placement.flip = 1;

            if (scaleInput) scaleInput.value = 100;
            if (rotationInput) rotationInput.value = 0;
            if (flipButton) {
                flipButton.setAttribute('aria-pressed', 'false');
                flipButton.classList.remove('active');
            }

            if (status) {
                status.textContent = measuredRoom ? 'Dimension-based fit' : 'Approximate fit';
            }
            if (dimensions) {
                const furnitureSize = [furnitureWidth, furnitureDepth, furnitureHeight]
                    .map((value) => value ? Number(value).toLocaleString('en-PH', { maximumFractionDigits: 1 }) : '?')
                    .join(' × ');
                const roomSize = roomWidth > 0
                    ? roomWidth.toLocaleString('en-PH', { maximumFractionDigits: 1 }) + ' × ' + (roomDepth || '?') + ' m room'
                    : 'Add room measurements for more accurate scale';
                dimensions.textContent = furnitureSize + ' cm furniture · ' + roomSize;
            }

            renderPlacement();
        };

        const updatePosition = (event) => {
            const bounds = comparison.getBoundingClientRect();
            placement.x = clamp(((event.clientX - bounds.left) / bounds.width) * 100, 4, 96);
            placement.y = clamp(((event.clientY - bounds.top) / bounds.height) * 100, 20, 96);
            renderPlacement();
        };

        range.addEventListener('input', () => setComparison(range.value));
        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const values = { original: 100, split: 50, result: 0 };
                setComparison(values[button.dataset.compareMode], button.dataset.compareMode);
            });
        });

        comparison.addEventListener('pointerdown', (event) => {
            if (!product || event.button !== 0) return;
            placement.dragging = true;
            comparison.setPointerCapture(event.pointerId);
            comparison.classList.add('is-positioning');
            comparison.focus({ preventScroll: true });
            setComparison(0, 'result');
            updatePosition(event);
        });
        comparison.addEventListener('pointermove', (event) => {
            if (placement.dragging) updatePosition(event);
        });
        const finishDragging = (event) => {
            if (!placement.dragging) return;
            placement.dragging = false;
            comparison.classList.remove('is-positioning');
            if (comparison.hasPointerCapture(event.pointerId)) comparison.releasePointerCapture(event.pointerId);
        };
        comparison.addEventListener('pointerup', finishDragging);
        comparison.addEventListener('pointercancel', finishDragging);
        comparison.addEventListener('keydown', (event) => {
            const movement = event.shiftKey ? 2 : 0.5;
            const directions = {
                ArrowLeft: [-movement, 0],
                ArrowRight: [movement, 0],
                ArrowUp: [0, -movement],
                ArrowDown: [0, movement],
            };
            if (!directions[event.key]) return;
            event.preventDefault();
            placement.x = clamp(placement.x + directions[event.key][0], 4, 96);
            placement.y = clamp(placement.y + directions[event.key][1], 20, 96);
            renderPlacement();
        });

        scaleInput?.addEventListener('input', () => {
            placement.scale = Number(scaleInput.value) / 100;
            renderPlacement();
        });
        rotationInput?.addEventListener('input', () => {
            placement.rotation = Number(rotationInput.value);
            renderPlacement();
        });
        document.querySelectorAll('[data-rotate-product]').forEach((button) => {
            button.addEventListener('click', () => {
                placement.rotation = clamp(placement.rotation + Number(button.dataset.rotateProduct), -45, 45);
                if (rotationInput) rotationInput.value = placement.rotation;
                renderPlacement();
            });
        });
        flipButton?.addEventListener('click', () => {
            placement.flip *= -1;
            const flipped = placement.flip === -1;
            flipButton.setAttribute('aria-pressed', String(flipped));
            flipButton.classList.toggle('active', flipped);
            renderPlacement();
        });
        blendButton?.addEventListener('click', () => {
            placement.blend = !placement.blend;
            blendButton.setAttribute('aria-pressed', String(placement.blend));
            blendButton.classList.toggle('active', placement.blend);
            renderPlacement();
        });
        document.querySelector('[data-fit-room]')?.addEventListener('click', () => {
            const option = select?.selectedOptions[0];
            if (option) describeFit(option);
            setComparison(0, 'result');
        });

        if (select) {
            const updateProduct = () => {
                const option = select.selectedOptions[0];
                if (!option) return;
                if (product) product.src = option.dataset.image;
                document.querySelectorAll('[data-preview-name]').forEach((element) => element.textContent = option.dataset.name);

                const cardImage = document.querySelector('[data-preview-card-image]');
                if (cardImage) {
                    cardImage.src = option.dataset.image;
                    cardImage.alt = option.dataset.name;
                }

                const price = document.querySelector('[data-preview-price]');
                if (price) price.textContent = Number(option.dataset.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const score = document.querySelector('[data-preview-score]');
                if (score) {
                    score.hidden = !option.dataset.score;
                    score.textContent = option.dataset.score ? Number(option.dataset.score).toFixed(1) + ' fit · ' : '';
                }

                const link = document.querySelector('[data-preview-product-link]');
                if (link) link.href = option.dataset.url;
                describeFit(option);
            };

            select.addEventListener('change', updateProduct);
            updateProduct();
        }

        setComparison(Number(range.value), 'split');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
});
