(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name=csrf]').content;

    const $ = id => document.getElementById(id);
    const exerciseList = $('exercise-list');
    const routineList  = $('routine-list');
    const searchInput  = $('search');
    const bodyPartSel  = $('filter-bodypart');
    const equipmentSel = $('filter-equipment');
    const resultCount  = $('result-count');
    const routineName  = $('routine-name');
    const loadSelect   = $('load-routine');
    const btnSave      = $('btn-save');
    const btnNew       = $('btn-new');
    const btnDelete    = $('btn-delete');
    const statusEl     = $('status');

    let currentRoutineId = null;
    let searchTimer = null;

    /* ---------- exercise catalogue ------------------------------------ */
    async function loadExercises() {
        const params = new URLSearchParams({
            action: 'search',
            q: searchInput.value.trim(),
            bodyPart: bodyPartSel.value,
            equipment: equipmentSel.value,
        });
        try {
            const data = await (await fetch(`api.php?${params}`)).json();
            resultCount.textContent = `${data.length} exercise${data.length === 1 ? '' : 's'}`;
            exerciseList.innerHTML = data.map(ex => `
                <div class="exercise-card" data-id="${ex.id}">
                    <div class="ex-name">${esc(ex.name)}</div>
                    <div class="ex-meta">
                        ${ex.body_part ? `<span class="tag">${esc(ex.body_part)}</span>` : ''}
                        ${ex.equipment ? `<span class="tag alt">${esc(ex.equipment)}</span>` : ''}
                    </div>
                </div>`).join('');
        } catch (err) {
            exerciseList.innerHTML = '<p class="error">Failed to load exercises.</p>';
        }
    }
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadExercises, 220);
    });
    bodyPartSel.addEventListener('change', loadExercises);
    equipmentSel.addEventListener('change', loadExercises);
    loadExercises();

    /* ---------- routine list in the select ---------------------------- */
    async function loadRoutineList() {
        const rows = await (await fetch('api.php?action=routines')).json();
        loadSelect.innerHTML = '<option value="">— New routine —</option>';
        rows.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = `${r.name} (${r.exercise_count})`;
            loadSelect.appendChild(opt);
        });
    }
    loadRoutineList();

    /* ---------- load an existing routine ------------------------------ */
    async function loadRoutine(id) {
        if (!id) {
            currentRoutineId = null;
            routineName.value = 'My workout';
            routineList.innerHTML = '<li class="empty-state">No exercises yet — drag some in from the catalogue.</li>';
            btnDelete.hidden = true;
            return;
        }
        const routine = await (await fetch(`api.php?action=routine&id=${id}`)).json();
        if (!routine || routine.error) return showStatus('Could not load routine.', 'error');

        currentRoutineId = routine.id;
        routineName.value = routine.name;
        btnDelete.hidden = false;

        routineList.innerHTML = routine.exercises.length
            ? routine.exercises.map(ex => cardHtml(ex, true)).join('')
            : '<li class="empty-state">This routine has no exercises yet.</li>';
    }
    loadSelect.addEventListener('change', () => loadRoutine(loadSelect.value));

    const urlLoad = new URLSearchParams(location.search).get('load');
    if (urlLoad) { loadSelect.value = urlLoad; loadRoutine(urlLoad); }

    /* ---------- drag and drop ----------------------------------------- */
    new Sortable(exerciseList, {
        group: { name: 'exercises', pull: 'clone', put: false },
        sort: false, animation: 150, ghostClass: 'drag-ghost',
    });

    new Sortable(routineList, {
        group: { name: 'exercises', pull: false, put: true },
        animation: 150, ghostClass: 'drag-ghost',
        onAdd: evt => {
            routineList.querySelector('.empty-state')?.remove();
            evt.item.insertAdjacentHTML('beforeend', inputsHtml());
        },
    });

    /* ---------- save --------------------------------------------------- */
    btnSave.addEventListener('click', async () => {
        const name = routineName.value.trim();
        if (!name) return showStatus('Give the routine a name first.', 'error');

        const cards = [...routineList.querySelectorAll('.exercise-card')];
        if (!cards.length) return showStatus('Add at least one exercise.', 'error');

        const exercises = cards.map((card, i) => ({
            id: card.dataset.id,
            position: i,
            sets:     Number(card.querySelector('.ex-sets')?.value  ?? 3),
            reps:     card.querySelector('.ex-reps')?.value ?? '8-12',
            rest_sec: Number(card.querySelector('.ex-rest')?.value  ?? 90),
        }));

        const body = { name, exercises };
        if (currentRoutineId !== null) body.id = currentRoutineId;

        const res = await fetch('api.php?action=save-routine', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) return showStatus(data.error || 'Save failed', 'error');

        currentRoutineId = data.routine_id;
        btnDelete.hidden = false;
        showStatus('Routine saved ✓', 'ok');
        await loadRoutineList();
        loadSelect.value = String(currentRoutineId);
    });

    /* ---------- new / delete ------------------------------------------ */
    btnNew.addEventListener('click', () => { loadSelect.value = ''; loadRoutine(''); showStatus('', ''); });

    btnDelete.addEventListener('click', async () => {
        if (currentRoutineId === null) return;
        if (!confirm('Delete this routine permanently?')) return;

        await fetch(`api.php?action=delete-routine&id=${currentRoutineId}`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
        });
        currentRoutineId = null;
        btnDelete.hidden = true;
        loadSelect.value = '';
        routineName.value = 'My workout';
        routineList.innerHTML = '<li class="empty-state">No exercises yet — drag some in from the catalogue.</li>';
        await loadRoutineList();
        showStatus('Routine deleted.', 'ok');
    });

    /* ---------- helpers ----------------------------------------------- */
    function inputsHtml(sets = 3, reps = '8-12', rest = 90) {
        return `<div class="set-rep">
            <label>Sets <input type="number" class="ex-sets" value="${sets}" min="1" max="20"></label>
            <label>Reps <input type="text" class="ex-reps" value="${esc(String(reps))}" maxlength="16"></label>
            <label>Rest <input type="number" class="ex-rest" value="${rest}" min="0" max="900"><span>s</span></label>
        </div>`;
    }

    function cardHtml(ex, withInputs = false) {
        return `<li class="exercise-card" data-id="${ex.id}">
            <div class="ex-name">${esc(ex.name)}</div>
            <div class="ex-meta">
                ${ex.body_part ? `<span class="tag">${esc(ex.body_part)}</span>` : ''}
                ${ex.equipment ? `<span class="tag alt">${esc(ex.equipment)}</span>` : ''}
            </div>
            ${withInputs ? inputsHtml(ex.sets ?? 3, ex.reps ?? '8-12', ex.rest_sec ?? 90) : ''}
        </li>`;
    }

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    function showStatus(msg, type) {
        statusEl.textContent = msg;
        statusEl.className = 'status' + (type ? ' ' + type : '');
        if (msg) setTimeout(() => { statusEl.textContent = ''; statusEl.className = 'status'; }, 3500);
    }
})();