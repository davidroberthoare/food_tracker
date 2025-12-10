<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Meal Tracker (PHP/SQLite)</title>
    
    <!-- Materialize CSS CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    
    <!-- Font Awesome (Retained for Icons) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js CDN for graphing -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    
    <style>
        /* Custom styles to adjust Materialize for better spacing and modern look */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap');
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f5f5f5; /* Light gray background */
        }
        .app-header {
            background-color: #3f51b5; /* Indigo 500 */
        }
        .container {
            width: 90%; /* Use a percentage width */
            max-width: 450px; /* Limit width like the previous version */
        }
        main .container {
            padding-top: 20px;
        }
        .card {
            border-radius: 12px; /* Smoother corners */
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .draggable-source { 
            cursor: grab; 
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .draggable-source:hover {
            background-color: #e8eaf6; /* Lightest indigo for hover */
        }
        .dragging { opacity: 0.5; }
        .drop-zone { transition: background 0.2s; }
        .drop-zone.drag-over {
            background-color: #f0f4c3; /* Light lime/green for drag over */
            border: 2px dashed #cddc39;
        }
        .drop-zone.drag-over .drop-placeholder {
            display: block;
        }
        .input-field label {
            font-size: 0.8rem;
        }
        .input-field textarea {
            min-height: 4rem; /* Ensure visibility for single-line text */
        }
        /* Sticky element adjustment for Materialize Navbar */
        .sticky-top-ai {
            position: sticky;
            top: 64px; /* Below the fixed navbar */
            z-index: 40;
            margin-bottom: 20px;
        }
        .meal-item-details {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            font-size: 0.8rem;
            color: #757575;
        }
        .day-totals {
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    
    <!-- Header (Materialize Navbar) -->
    <nav class="app-header z-depth-2">
        <div class="nav-wrapper container">
            <a href="#!" class="brand-logo left"><i class="fas fa-carrot left"></i>NutriTrack</a>
            <ul class="right">
                <li><span class="text-xs font-light opacity-80" id="connectionStatus">Ready</span></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <div class="container">
            
            <!-- Chart Container -->
            <div id="chartContainer" class="card white z-depth-1">
                <div class="card-content">
                    <span class="card-title" style="font-size: 1.2rem;">Daily Macro Summary</span>
                    <canvas id="dailyMacroChart"></canvas>
                    <div id="chartNoData" class="center-align grey-text text-lighten-1 hidden" style="display: none;">
                        Enter at least two days of data to see the trend graph.
                    </div>
                </div>
            </div>

            <!-- AI Input Section -->
            <div class="sticky-top-ai">
                <div class="card white z-depth-1" style="padding: 15px;">
                    <label class="grey-text text-darken-1" style="font-weight: 600;">Ask AI to Add Food</label>
                    <div class="row" style="margin-bottom: 0;">
                        <div class="col s10 input-field" style="margin-top: 0;">
                            <textarea id="aiInput" class="materialize-textarea" rows="2" placeholder="Yesterday for dinner I had pizza with ice cream for dessert" onkeydown="handleInputKeyDown(event)"></textarea>
                            <!-- <label for="aiInput">What did you eat?</label> -->
                        </div>
                        <div class="col s2" style="padding-top: 15px;">
                            <button onclick="processAI()" id="aiBtn" class="btn btn-floating waves-effect waves-light indigo darken-1">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Loading Indicator - Initialized with display: none -->
                    <div id="loader" class="center-align" style="margin-top: 8px; display: none;">
                        <div class="preloader-wrapper small active">
                            <div class="spinner-layer spinner-indigo-only">
                              <div class="circle-clipper left">
                                <div class="circle"></div>
                              </div><div class="gap-patch">
                                <div class="circle"></div>
                              </div><div class="circle-clipper right">
                                <div class="circle"></div>
                              </div>
                            </div>
                        </div>
                        <span class="indigo-text text-darken-1" style="font-size: 0.7rem; display: block;">Analyzing food & detecting meal...</span>
                    </div>
                </div>
            </div>

            <!-- Timeline Container -->
            <div id="timelineContainer" class="section">
                <div class="center-align grey-text" style="padding: 50px 0;">Loading your history...</div>
            </div>
            
            <!-- Intersection Observer Sentinel -->
            <div id="sentinel" style="height: 10px;"></div>

        </div>
    </main>

    <!-- Modal for Editing/Approving (Materialize Modal) -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="padding: 0;">
            <div class="indigo darken-1 white-text" style="padding: 15px 24px;">
                <h4 id="modalTitle" style="margin-top: 0; font-size: 1.5rem;">Edit Entry</h4>
            </div>
            <div style="padding: 24px; padding-bottom: 0;">
                <input type="hidden" id="editId">
                <input type="hidden" id="editDate"> <!-- Store date for the entry -->
                
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" id="editName">
                        <label for="editName">Food Name</label>
                    </div>
                </div>
                
                <div class="row">
                    <div class="input-field col s12">
                        <select id="editMeal">
                            <option value="Breakfast">Breakfast</option>
                            <option value="Lunch">Lunch</option>
                            <option value="Dinner">Dinner</option>
                            <option value="Snack">Snack</option>
                        </select>
                        <label>Meal</label>
                    </div>
                </div>

                <div class="row">
                    <div class="input-field col s6">
                        <input type="number" id="editCal">
                        <label for="editCal">Calories</label>
                    </div>
                    <div class="input-field col s6">
                        <input type="number" id="editPro">
                        <label for="editPro">Protein (g)</label>
                    </div>
                </div>

                <div class="row" style="margin-bottom: 0;">
                    <div class="input-field col s6">
                        <input type="number" id="editSug">
                        <label for="editSug">Sugar (g)</label>
                    </div>
                    <div class="input-field col s6">
                        <input type="number" id="editFat">
                        <label for="editFat">Fat (g)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding: 4px 20px;">
            <button onclick="deleteCurrentEntry()" id="btnDelete" class="btn-flat red-text text-darken-2 waves-effect">Delete</button>
            <button onclick="saveEntry()" class="modal-close btn waves-effect waves-light indigo darken-1">Save</button>
            <button onclick="closeModal()" class="modal-close btn-flat waves-effect">Cancel</button>
        </div>
    </div>
    
    <!-- Materialize JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

    <script>
        // --- State ---
        let allEntries = []; 
        let groupedEntries = {}; 
        let sortedDates = []; 
        let visibleDays = 5; 
        let dailyMacroChart = null; 
        let modalInstance = null;
        
        const API_URL = 'api.php';
        const MEALS = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
        const DAYS_TO_LOAD_INCREMENT = 5;

        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', function() {
            M.AutoInit();
            // Get Materialize modal instance
            const modalElement = document.getElementById('editModal');
            modalInstance = M.Modal.init(modalElement);
            
            // Initial data fetch
            fetchAllEntries();
        });

        // --- Data Fetching ---
        async function fetchAllEntries() {
            try {
                const response = await fetch(API_URL, { method: 'GET' });
                const result = await response.json();

                if (result.success && Array.isArray(result.data)) {
                    allEntries = result.data.map(e => ({
                        // Convert DB TEXT/INTEGER types back to JS types
                        id: e.id,
                        date: e.date,
                        food_name: e.food_name,
                        meal_type: e.meal_type,
                        calories: parseInt(e.calories) || 0,
                        protein: parseInt(e.protein) || 0,
                        sugar: parseInt(e.sugar) || 0,
                        fat: parseInt(e.fat) || 0
                    }));
                    
                    processEntries();
                    renderChart(); 
                    renderTimeline();
                } else {
                    throw new Error(result.error || 'Failed to fetch data.');
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                document.getElementById('timelineContainer').innerHTML = `<div class="red-text center-align">Error: ${error.message}</div>`;
                document.getElementById('connectionStatus').textContent = 'Error';
                M.toast({html: 'Could not connect to PHP API. Ensure api.php is running.', classes: 'red darken-1'});
            }
        }

        // --- Data Processing (Remains same) ---
        function processEntries() {
            groupedEntries = {};
            sortedDates = [];

            allEntries.forEach(entry => {
                if (!groupedEntries[entry.date]) {
                    groupedEntries[entry.date] = [];
                    sortedDates.push(entry.date);
                }
                groupedEntries[entry.date].push(entry);
            });
            
            // Remove duplicates and sort ASC for chart
            sortedDates = [...new Set(sortedDates)].sort((a, b) => new Date(a) - new Date(b)); 
        }
        
        // --- Chart Rendering (Remains same) ---
        function renderChart() {
            const ctx = document.getElementById('dailyMacroChart');
            const noData = document.getElementById('chartNoData');
            
            const chartData = sortedDates.map(date => {
                const dayEntries = groupedEntries[date] || [];
                return dayEntries.reduce((acc, curr) => ({
                    date: date,
                    cal: acc.cal + curr.calories,
                    pro: acc.pro + curr.protein,
                    sug: acc.sug + curr.sugar,
                    fat: acc.fat + curr.fat
                }), { date: date, cal: 0, pro: 0, sug: 0, fat: 0 });
            });
            
            if (chartData.length < 2) {
                ctx.style.display = 'none';
                noData.style.display = 'block';
                if (dailyMacroChart) {
                    dailyMacroChart.destroy();
                    dailyMacroChart = null;
                }
                return;
            }
            
            ctx.style.display = 'block';
            noData.style.display = 'none';


            if (dailyMacroChart) {
                dailyMacroChart.destroy();
            }

            dailyMacroChart = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: chartData.map(d => {
                        const dateObj = new Date(d.date + 'T12:00:00Z');
                        return dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
                    }),
                    datasets: [
                        {
                            label: 'Calories',
                            data: chartData.map(d => d.cal),
                            borderColor: 'rgb(63, 81, 181)', 
                            backgroundColor: 'rgba(63, 81, 181, 0.6)',
                            fill: false,
                            tension: 0.3,
                            type: 'line', 
                            yAxisID: 'y1', 
                            order: 1, 
                            pointRadius: 4, 
                        },
                        {
                            label: 'Protein ',
                            data: chartData.map(d => d.pro),
                            backgroundColor: 'rgb(76, 175, 80)', 
                            type: 'bar',
                            yAxisID: 'y', 
                            stack: 'gramsStack', 
                            order: 2,
                        },
                        {
                            label: 'Fat ',
                            data: chartData.map(d => d.fat),
                            backgroundColor: 'rgb(255, 193, 7)', 
                            type: 'bar',
                            yAxisID: 'y',
                            stack: 'gramsStack',
                            order: 2,
                        },
                        {
                            label: 'Sugar ',
                            data: chartData.map(d => d.sug),
                            backgroundColor: 'rgb(244, 67, 54)', 
                            type: 'bar',
                            yAxisID: 'y',
                            stack: 'gramsStack',
                            order: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 6,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        title: {
                            display: false,
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            stacked: true, 
                            title: {
                                display: true,
                                text: 'Macros (g)',
                                font: { size: 10 }
                            },
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right', 
                            title: {
                                display: true,
                                text: 'Calories',
                                font: { size: 10 }
                            },
                            grid: {
                                drawOnChartArea: false, 
                            },
                            beginAtZero: true,
                            ticks: {
                                color: 'rgb(63, 81, 181)', 
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
        }

        // --- Infinite Scroll Observer (Remains same) ---
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                if (visibleDays < sortedDates.length) {
                    visibleDays += DAYS_TO_LOAD_INCREMENT;
                    renderTimeline();
                }
            }
        }, { threshold: 0.1 });
        
        observer.observe(document.getElementById('sentinel'));

        // --- AI Processing ---
        window.processAI = async function() {
            const text = document.getElementById('aiInput').value;
            if (!text) return;

            document.getElementById('loader').style.display = 'block';
            document.getElementById('aiBtn').disabled = true;

            try {
                // Send text to PHP API for AI processing and insertion
                const apiResponse = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: text })
                });

                const apiResult = await apiResponse.json();

                if (apiResult.success) {
                    M.toast({html: 'Meals added successfully!', classes: 'green darken-1'});
                    document.getElementById('aiInput').value = '';
                    // Re-fetch data to update the UI
                    fetchAllEntries();
                } else {
                    throw new Error(apiResult.error || 'Failed to save meals.');
                }

            } catch (err) {
                console.error(err);
                M.toast({html: 'Could not process input or save: ' + err.message, classes: 'red darken-1'});
            } finally {
                document.getElementById('loader').style.display = 'none';
                document.getElementById('aiBtn').disabled = false;
            }
        };

        // --- Keyboard Handler for Enter Submission ---
        window.handleInputKeyDown = function(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault(); 
                window.processAI();
            }
        }

        // --- Database Operations (NEW fetch logic) ---
        window.saveEntry = async function() {
            const id = document.getElementById('editId').value;
            
            const data = {
                id: id ? parseInt(id) : null, // ID is required for PUT
                date: document.getElementById('editDate').value, 
                meal_type: document.getElementById('editMeal').value,
                food_name: document.getElementById('editName').value,
                calories: parseInt(document.getElementById('editCal').value) || 0,
                protein: parseInt(document.getElementById('editPro').value) || 0,
                sugar: parseInt(document.getElementById('editSug').value) || 0,
                fat: parseInt(document.getElementById('editFat').value) || 0,
            };

            // FIX: Ensure PUT request is used for updates (when ID is present)
            if (data.id) {
                try {
                    const response = await fetch(API_URL, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        M.toast({html: 'Entry saved!', classes: 'green darken-1'});
                        closeModal();
                        fetchAllEntries(); // Refresh UI
                    } else {
                        throw new Error(result.error || 'Update failed.');
                    }
                } catch (e) {
                    console.error("Error saving:", e);
                    M.toast({html: 'Error saving entry: ' + e.message, classes: 'red darken-1'});
                }
            } else {
                // If ID is missing, we could treat it as a new POST entry,
                // but for this UI, the modal is primarily for editing existing.
                M.toast({html: 'Cannot save a new entry via the edit modal without an ID.', classes: 'red darken-1'});
            }
        }

        window.deleteCurrentEntry = async function() {
            const id = document.getElementById('editId').value;
            if (!id) return;
            
            try {
                const response = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
                const result = await response.json();

                if (result.success) {
                    M.toast({html: 'Entry deleted!', classes: 'green darken-1'});
                    closeModal();
                    fetchAllEntries(); // Refresh UI
                } else {
                    throw new Error(result.error || 'Delete failed.');
                }
            } catch (e) {
                console.error("Error deleting:", e);
                M.toast({html: 'Error deleting entry: ' + e.message, classes: 'red darken-1'});
            }
        }

        window.updateEntryMeal = async function(id, newMeal, newDate) {
            // Fetch the existing item to send a full payload for the PUT request
            const existingEntry = allEntries.find(e => e.id == id);
            if (!existingEntry) return;

            const updatedData = {
                id: parseInt(id),
                date: newDate, 
                meal_type: newMeal,
                food_name: existingEntry.food_name,
                calories: existingEntry.calories,
                protein: existingEntry.protein,
                sugar: existingEntry.sugar,
                fat: existingEntry.fat,
            };
            
            try {
                const response = await fetch(API_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updatedData)
                });
                
                const result = await response.json();

                if (result.success) {
                    M.toast({html: `Moved to ${newMeal} on ${newDate.substring(5)}`, classes: 'blue darken-1'});
                    fetchAllEntries(); // Refresh UI
                } else {
                    throw new Error(result.error || 'Drag update failed.');
                }
            } catch (e) {
                console.error("Error updating drag:", e);
                M.toast({html: 'Error updating entry via drag and drop.', classes: 'red darken-1'});
            }
        }

        // --- UI & Rendering (mostly same) ---
        function renderTimeline() {
            // ... (Timeline rendering logic remains the same, relies on allEntries/groupedEntries)
            const container = document.getElementById('timelineContainer');
            
            if (sortedDates.length === 0) {
                container.innerHTML = `<div class="center-align grey-text" style="padding: 50px 0;">No meals tracked yet. <br> Ask the AI above to add something!</div>`;
                return;
            }

            container.innerHTML = '';
            
            // Render only visible days, most recent first
            const daysToRender = sortedDates.slice().reverse().slice(0, visibleDays); 

            daysToRender.forEach(date => {
                const dayEntries = groupedEntries[date];
                const dayTotals = dayEntries.reduce((acc, curr) => ({
                    cal: acc.cal + curr.calories,
                    pro: acc.pro + curr.protein,
                    sug: acc.sug + curr.sugar,
                    fat: acc.fat + curr.fat
                }), { cal: 0, pro: 0, sug: 0, fat: 0 });

                // Date Formatting
                const dateObj = new Date(date + 'T12:00:00Z'); 
                const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long', timeZone: 'UTC' });
                const fullDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
                const isToday = date === new Date().toISOString().split('T')[0];

                const dayCard = document.createElement('div');
                dayCard.className = "card white z-depth-1";
                dayCard.dataset.date = date; 
                
                let dayHeaderHTML = `
                    <div class="card-content" style="padding-bottom: 5px;">
                        <div class="row" style="margin-bottom: 0;">
                            <div class="col s12 m6">
                                <span class="card-title" style="font-size: 1.2rem; font-weight: 600;">
                                    ${isToday ? 'Today' : dayName} 
                                    <small class="grey-text text-lighten-1" style="font-size: 0.75em;">${fullDate}</small>
                                </span>
                            </div>
                            <div class="col s12 m6 right-align">
                                <span class="indigo-text text-darken-1 day-totals" style="font-weight: 600;">Cal: ${dayTotals.cal}</span>
                                <span class="grey-text text-darken-1 day-totals" style="margin-left: 10px;">P: ${dayTotals.pro}g</span>
                                <span class="grey-text text-darken-1 day-totals" style="margin-left: 10px;">S: ${dayTotals.sug}g</span>
                                <span class="grey-text text-darken-1 day-totals" style="margin-left: 10px;">F: ${dayTotals.fat}g</span>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 0 10px 10px 10px;">
                `;

                // Render Meals within the day (Compact)
                let mealsHTML = '';
                MEALS.forEach(meal => {
                    const mealItems = dayEntries.filter(e => e.meal_type === meal);
                    const mealCal = mealItems.reduce((sum, e) => sum + e.calories, 0);

                    mealsHTML += `
                        <div class="drop-zone meal-slot" data-meal="${meal}" style="border: 1px solid #eee; margin-top: 10px; padding: 5px; border-radius: 6px;">
                            <div class="valign-wrapper grey-text" style="padding: 0 5px; margin-bottom: 5px; justify-content: space-between;">
                                <span class="meal-title" style="font-weight: bold; font-size: 0.8rem; text-transform: uppercase;">${meal}</span>
                                <span style="font-size: 0.8rem;">${mealCal} cal</span>
                            </div>
                            <div class="meal-items-list">
                    `;

                    if (mealItems.length === 0) {
                         mealsHTML += `<div class="center-align grey-text text-lighten-2 drop-placeholder" style="font-size: 0.6rem; padding: 5px 0; line-height: 1; display: none;">Drop item here</div>`;
                    } else {
                        mealItems.forEach(item => {
                            mealsHTML += `
                                <div class="draggable-source white z-depth-1"
                                     draggable="true" 
                                     data-id="${item.id}"
                                     onclick='openEditModal(${JSON.stringify(item)})'>
                                    <div class="row valign-wrapper" style="margin-bottom: 0;">
                                        <div class="col s12 m6">
                                            <span class="black-text" style="font-weight: 500; font-size: 0.9rem;">${item.food_name}</span>
                                        </div>
                                        <div class="col s12 m6 meal-item-details">
                                            <span class="indigo-text text-darken-1">${item.calories} cal</span>
                                            <span>P: ${item.protein}g</span>
                                            <span>S: ${item.sugar}g</span>
                                            <span>F: ${item.fat}g</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    mealsHTML += `</div></div>`;
                });


                dayCard.innerHTML = dayHeaderHTML + mealsHTML + `</div>`;
                
                // Attach Event Listeners for this card's items
                const draggables = dayCard.querySelectorAll('.draggable-source');
                draggables.forEach(el => {
                    el.addEventListener('dragstart', handleDragStart);
                    el.addEventListener('dragend', handleDragEnd);
                });
                
                const dropZones = dayCard.querySelectorAll('.drop-zone');
                dropZones.forEach(el => {
                    el.addEventListener('dragover', e => {
                        e.preventDefault();
                        el.classList.add('drag-over');
                    });
                    el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
                    el.addEventListener('drop', (e) => {
                        const targetCard = e.currentTarget.closest('.card');
                        const targetDate = targetCard ? targetCard.dataset.date : null;
                        
                        if (targetDate) {
                            handleDrop(e, el.dataset.meal, targetDate); 
                        } else {
                            console.error("Could not find target date for drop.");
                        }
                    });
                });

                container.appendChild(dayCard);
            });
        }

        // --- Drag and Drop Logic (same) ---
        let draggedId = null;

        function handleDragStart(e) {
            draggedId = this.dataset.id;
            this.classList.add('dragging');
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            document.querySelectorAll('.drop-zone').forEach(z => z.classList.remove('drag-over'));
        }

        function handleDrop(e, targetMeal, targetDate) {
            e.preventDefault();
            const zone = e.currentTarget;
            zone.classList.remove('drag-over');
            
            if (draggedId) {
                window.updateEntryMeal(draggedId, targetMeal, targetDate);
                draggedId = null;
            }
        }

        // --- Modals (same) ---
        window.openEditModal = function(entry) {
            if (typeof entry === 'string') entry = JSON.parse(entry); 
            
            document.getElementById('modalTitle').textContent = entry ? "Edit Entry" : "Add Entry";
            document.getElementById('editId').value = entry ? entry.id : '';
            document.getElementById('editDate').value = entry ? entry.date : new Date().toISOString().split('T')[0];
            document.getElementById('editName').value = entry ? entry.food_name : '';
            document.getElementById('editMeal').value = entry ? entry.meal_type : 'Snack';
            document.getElementById('editCal').value = entry ? entry.calories : 0;
            document.getElementById('editPro').value = entry ? entry.protein : 0;
            document.getElementById('editSug').value = entry ? entry.sugar : 0;
            document.getElementById('editFat').value = entry ? entry.fat : 0;
            
            document.getElementById('btnDelete').style.display = entry ? 'inline-block' : 'none';

            M.FormSelect.init(document.getElementById('editMeal'));

            if (modalInstance) {
                modalInstance.open();
                M.updateTextFields(); 
            } else {
                 console.error("Materialize Modal not initialized.");
            }
        }

        window.closeModal = function() {
            if (modalInstance) {
                modalInstance.close();
            }
        }
    </script>
</body>
</html>