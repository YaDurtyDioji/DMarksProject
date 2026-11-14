(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name=csrf]').content;
    const $ = id => document.getElementById(id);

    // --- Theme Toggle ---
    const themeBtn = $('theme-toggle');
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeIcon(savedTheme);

    themeBtn.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        themeBtn.innerHTML = theme === 'dark' ? '<i class="bi bi-moon-stars-fill"></i>' : '<i class="bi bi-sun-fill"></i>';
    }

    // --- Elements ---
    const exerciseList = $('exercise-list');
    const routineList  = $('routine-list');
    const emptyState   = $('empty-state');
    const searchInput  = $('search');
    const bodyPartSel  = $('filter-bodypart');
    const categorySel  = $('filter-category');
    const resultCount  = $('result-count');
    const routineName  = $('routine-name');
    const loadSelect   = $('load-routine');
    const btnSave      = $('btn-save');
    const btnDelete    = $('btn-delete');
    const statusEl     = $('status');
    const userWeight   = $('user-weight');

    let currentRoutineId = null;
    let searchTimer = null;

    // --- Load Exercises ---
    async function loadExercises() {
        const params = new URLSearchParams({
            action: 'search',
            q: searchInput.value.trim(),
            bodyPart: bodyPartSel.value,
            category: categorySel.value,
        });
        try {
            const data = await (await fetch(`api.php?${params}`)).json();
            resultCount.textContent = `${data.length.toLocaleString()} exercises`;

            if (!data.length) {
                exerciseList.innerHTML = `<div class="text-center text-muted p-4"><i class="bi bi-search fs-3"></i><br>No results</div>`;
                return;
            }
            exerciseList.innerHTML = data.map(catalogueCardHtml).join('');
        } catch (err) {
            exerciseList.innerHTML = `<div class="alert alert-danger m-0">Error loading exercises.</div>`;
        }
    }

    searchInput.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadExercises, 220); });
    bodyPartSel.addEventListener('change', loadExercises);
    categorySel.addEventListener('change', loadExercises);
    loadExercises();

    // --- Drag & Drop ---
    
    // 1. Catalogue (Clone Source)
    new Sortable(exerciseList, {
        group: { name: 'catalogue', pull: 'clone', put: false },
        sort: false,
        animation: 150,
    });

    // 2. Routine List (Handles Blocks)
    new Sortable(routineList, {
        group: 'blocks',
        handle: '.block-handle',
        animation: 150,
        onAdd: function (evt) {
            // If we dragged an exercise directly into the main list (not a block)
            if (evt.item.classList.contains('exercise-card')) {
                const card = evt.item;
                // Remove it from the DOM immediately, we will rebuild it properly
                card.remove(); 
                
                // Create a new block and put the card in it
                const newBlock = createBlock();
                const exerciseContainer = newBlock.querySelector('.block-exercises');
                exerciseContainer.appendChild(buildRoutineCard(card.dataset));
                routineList.appendChild(newBlock);
                
                removeEmptyState();
                checkEmpty();
            }
        }
    });

    // 3. Inner Block Lists (Handles exercises inside a block)
    function makeBlockSortable(block) {
        new Sortable(block.querySelector('.block-exercises'), {
            group: { name: 'inner-exercises', pull: true, put: true },
            handle: '.drag-handle',
            animation: 150,
            onAdd: function (evt) {
                // If we dragged from the catalogue into an existing block
                if (evt.from === exerciseList) {
                    const newCard = buildRoutineCard(evt.item.dataset);
                    evt.item.remove(); // remove the cloned catalogue item
                    evt.to.insertBefore(newCard, evt.to.children[evt.newIndex]);
                    removeEmptyState();
                }
            },
            onRemove: function (evt) {
                // If a block becomes empty, remove it
                if (!evt.from.querySelector('.exercise-card')) {
                    evt.from.closest('.superset-block').remove();
                    checkEmpty();
                }
            }
        });
    }

    // Helper: Create a Superset Block
    function createBlock() {
        const block = document.createElement('div');
        block.className = 'superset-block mb-3';
        block.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2 px-2 pt-1">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-grip-vertical drag-handle block-handle"></i>
                    <span class="fw-bold text-primary small text-uppercase">Block</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-block"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="block-exercises sortable-nested p-1"></div>
        `;
        block.querySelector('.remove-block').addEventListener('click', () => {
            block.remove();
            checkEmpty();
        });
        makeBlockSortable(block);
        return block;
    }

    // Helper: Build the detailed card with sets/weight/RPE
    function buildRoutineCard(data) {
        const card = document.createElement('div');
        card.className = 'exercise-card card border-secondary-subtle bg-body-tertiary mb-2';
        card.dataset.id = data.id;
        card.dataset.category = data.category || '';
        card.dataset.equipment = data.equipment || '';

        const met = getMetEstimate(data.category, data.equipment);

        card.innerHTML = `
            <div class="card-body p-2">
                <div class="exercise-header d-flex align-items-center">
                    <i class="bi bi-grip-vertical drag-handle me-2"></i>
                    <span class="fw-semibold small flex-grow-1">${esc(data.name)}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-card"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="sets-container mt-2"></div>
            </div>
        `;

        card.querySelector('.remove-card').addEventListener('click', () => {
            const block = card.closest('.superset-block');
            card.remove();
            if (block && !block.querySelector('.exercise-card')) block.remove();
            checkEmpty();
        });

        renderSets(card.querySelector('.sets-container'), met);
        return card;
    }

    // Helper: Render the sets inputs
    function renderSets(container, met) {
        const weight = parseFloat(userWeight.value) || 75;
        let setsCount = 3;

        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="small text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Sets</label>
                <input type="number" class="form-control form-control-sm set-count-input" style="width: 60px;" value="${setsCount}" min="1" max="10">
            </div>
            <div class="set-rows"></div>
            <div class="text-end small text-secondary mt-1">
                <i class="bi bi-fire text-warning"></i> ~<span class="cal-total">0</span> kcal
            </div>
        `;

        const rowsContainer = container.querySelector('.set-rows');
        const calSpan = container.querySelector('.cal-total');
        const countInput = container.querySelector('.set-count-input');

        function drawRows(count) {
            rowsContainer.innerHTML = '';
            for (let i = 1; i <= count; i++) {
                rowsContainer.insertAdjacentHTML('beforeend', `
                    <div class="set-row">
                        <div class="set-num">${i}</div>
                        <input type="number" class="form-control form-control-sm set-weight" placeholder="kg">
                        <input type="text" class="form-control form-control-sm set-reps" placeholder="reps">
                        <input type="number" class="form-control form-control-sm set-rpe" placeholder="RPE" min="1" max="10" step="0.5">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-set" title="Remove Set"><i class="bi bi-dash-circle"></i></button>
                    </div>
                `);
            }
            bindRowEvents();
            recalc();
        }

        function bindRowEvents() {
            rowsContainer.querySelectorAll('.remove-set').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.target.closest('.set-row').remove();
                    recalc();
                });
            });
        }

        function recalc() {
            const activeRows = rowsContainer.querySelectorAll('.set-row').length;
            countInput.value = activeRows;
            
            // MET calculation: MET * Weight(kg) * Hours
            const durationHours = (activeRows * 1.5) / 60; // approx 1.5 mins per set
            const cals = Math.round(met * weight * durationHours);
            calSpan.textContent = cals;
        }

        countInput.addEventListener('change', (e) => {
            drawRows(Math.max(1, parseInt(e.target.value) || 1));
        });

        drawRows(setsCount);
    }

    function checkEmpty() {
        if (!routineList.querySelector('.exercise-card')) {
            emptyState.style.display = 'flex';
        } else {
            emptyState.style.display = 'none';
        }
    }

    function removeEmptyState() {
        emptyState.style.display = 'none';
    }

    // --- Save Routine ---
    btnSave.addEventListener('click', async () => {
        const blocks = routineList.querySelectorAll('.superset-block');
        if (!blocks.length) return showStatus('Add at least one exercise.', 'error');

        const exercises = [];
        let position = 0;

        blocks.forEach((block, bIndex) => {
            const cards = block.querySelectorAll('.exercise-card');
            cards.forEach((card, cIndex) => {
                const sets = [];
                card.querySelectorAll('.set-row').forEach(row => {
                    sets.push({
                        weight: row.querySelector('.set-weight').value,
                        reps: row.querySelector('.set-reps').value,
                        rpe: row.querySelector('.set-rpe').value
                    });
                });

                exercises.push({
                    id: card.dataset.id,
                    position: position++,
                    block_id: bIndex,
                    block_order: cIndex,
                    sets_data: sets
                });
            });
        });

        const body = { name: routineName.value.trim(), exercises };
        if (currentRoutineId !== null) body.id = currentRoutineId;

        btnSave.disabled = true;
        try {
            const res = await fetch('api.php?action=save-routine', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Save failed');
            
            currentRoutineId = data.routine_id;
            btnDelete.hidden = false;
            showStatus('Routine saved successfully.', 'ok');
        } catch (err) {
            showStatus(err.message || 'Error saving', 'error');
        } finally {
            btnSave.disabled = false;
        }
    });

    // --- Helpers ---
    function catalogueCardHtml(ex) {
        return `
            <div class="exercise-card card border-secondary-subtle bg-body-tertiary mb-2" 
                 data-id="${ex.id}" data-name="${esc(ex.name)}" data-category="${ex.category}" data-equipment="${ex.equipment}">
                <div class="card-body p-2">
                    <div class="fw-semibold small">${esc(ex.name)}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        ${ex.body_part ? `<span class="badge bg-primary-subtle text-primary-emphasis fw-normal">${esc(ex.body_part)}</span>` : ''}
                        ${ex.category ? `<span class="badge bg-warning-subtle text-warning-emphasis fw-normal">${esc(ex.category)}</span>` : ''}
                    </div>
                </div>
            </div>`;
    }

    function getMetEstimate(category, equipment) {
        category = (category || '').toLowerCase();
        equipment = (equipment || '').toLowerCase();
        if (category.includes('cardio') || category.includes('plyometrics')) return 8.0;
        if (category.includes('yoga') || category.includes('stretching')) return 3.0;
        if (category.includes('pilates')) return 3.5;
        if (category.includes('strength')) return equipment.includes('body') ? 4.0 : 6.0;
        return 5.0;
    }

    function showStatus(msg, type) {
        if (!msg) { statusEl.innerHTML = ''; return; }
        const cls  = type === 'ok' ? 'success' : 'danger';
        statusEl.innerHTML = `<div class="alert alert-${cls} alert-dismissible fade show mt-3 py-2" role="alert">
            <span class="small">${esc(msg)}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        setTimeout(() => statusEl.innerHTML = '', 3500);
    }

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
})();