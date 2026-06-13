document.addEventListener("DOMContentLoaded", function() {
    // 1. Prevent double submission of forms
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        form.addEventListener("submit", function() {
            const submitBtn = this.querySelector("button[type='submit']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = "Processing...";
                submitBtn.style.opacity = "0.7";
            }
        });
    });

    // 2. Fade out alert success/error blocks after 4s
    const alerts = document.querySelectorAll(".alert-success, .alert-error");
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // 3. Universal Info Modal Logic (Footer link triggers)
    const infoModal = document.getElementById("infoModal");
    const closeInfoModalBtn = document.getElementById("closeInfoModalBtn");
    const infoModalTitle = document.getElementById("infoModalTitle");
    const infoModalBody = document.getElementById("infoModalBody");

    const modalContents = {
        pivot: {
            title: "Rating Pivot Calculation",
            body: `
                <p style="margin-bottom: 15px;">To enable unified difficulty tracking across multiple competitive programming platforms, CProg Tracker translates platform-specific metrics into a single Codeforces-equivalent scale:</p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li style="margin-bottom: 8px;"><strong>Codeforces</strong>: Stays 1:1 using the official difficulty ratings (e.g. 800, 1200, 1600, 2400).</li>
                    <li style="margin-bottom: 8px;"><strong>LeetCode</strong>: Maps standard problem difficulty categories to fixed equivalents:
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <li><span style="color: #2ecc71; font-weight: bold;">Easy</span> &rarr; 800 Rating</li>
                            <li><span style="color: #facc15; font-weight: bold;">Medium</span> &rarr; 1200 Rating</li>
                            <li><span style="color: #ec4899; font-weight: bold;">Hard</span> &rarr; 1600 Rating</li>
                        </ul>
                    </li>
                    <li style="margin-bottom: 8px;"><strong>AtCoder / Others</strong>: Maps roughly based on platform grading (e.g., AtCoder Grey &approx; 800, Brown &approx; 1200).</li>
                </ul>
                <p>This allows the system to compute your average capability rating and suggest optimal problem recommendations across all linked platforms.</p>
            `
        },
        guide: {
            title: "How to Use CProg Tracker",
            body: `
                <ol style="margin-left: 20px; margin-bottom: 15px;">
                    <li style="margin-bottom: 10px;"><strong>Add Platform Handles</strong>: Go to <em>Settings</em>, choose a platform (like Codeforces), and enter your username to synchronize your solve history.</li>
                    <li style="margin-bottom: 10px;"><strong>Track Progress</strong>: The dashboard shows visual rating charts and difficulty trends generated from your submissions.</li>
                    <li style="margin-bottom: 10px;"><strong>Manual Submissions</strong>: Use the "Add Custom Problem" modal to manually add solutions from other competitive programming sites and upload proof screenshots.</li>
                    <li style="margin-bottom: 10px;"><strong>Smart Recommendations</strong>: Follow the adaptive recommendations on your dashboard. They are tailored to target problems slightly above your current average rating to help you level up faster.</li>
                </ol>
            `
        }
    };

    document.querySelectorAll(".footer-modal-trigger").forEach(trigger => {
        trigger.addEventListener("click", function(e) {
            e.preventDefault();
            const type = this.getAttribute("data-type");
            if (modalContents[type] && infoModal) {
                infoModalTitle.textContent = modalContents[type].title;
                infoModalBody.innerHTML = modalContents[type].body;
                infoModal.style.display = "flex";
                document.body.style.overflow = "hidden";
            }
        });
    });

    if (closeInfoModalBtn && infoModal) {
        closeInfoModalBtn.addEventListener("click", function(e) {
            e.preventDefault();
            infoModal.style.display = "none";
            document.body.style.overflow = "auto";
        });
    }

    // 4. Custom Problem Modal Logic (Dashboard)
    const customModal = document.getElementById("customProblemModal");
    const openCustomModalBtn = document.getElementById("openModalBtn");
    const closeCustomModalBtn = document.getElementById("closeModalBtn");

    if (openCustomModalBtn && customModal) {
        openCustomModalBtn.addEventListener("click", function() {
            customModal.style.display = "flex";
            document.body.style.overflow = "hidden";
        });
    }

    if (closeCustomModalBtn && customModal) {
        closeCustomModalBtn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            customModal.style.display = "none";
            document.body.style.overflow = "auto";
        });
    }

    window.addEventListener("click", function(event) {
        if (event.target === infoModal) {
            infoModal.style.display = "none";
            document.body.style.overflow = "auto";
        }
        if (event.target === customModal) {
            customModal.style.display = "none";
            document.body.style.overflow = "auto";
        }
    });

    // 5. Chart.js Graphs (Dynamic initialization from HTML attributes)
    if (typeof Chart !== 'undefined') {
        Chart.defaults.color = '#a1a1aa';
        Chart.defaults.scale.grid.color = '#333333';

        const WINDOW_SIZE = 40; // Number of points to show per page

        function initPaginatedChart(canvasId, prevBtnId, nextBtnId, labelStr, borderColor, bgColor, pointColor) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const allLabels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
            const allValues = JSON.parse(canvas.getAttribute('data-values') || '[]');
            const prevBtn = document.getElementById(prevBtnId);
            const nextBtn = document.getElementById(nextBtnId);

            // Start so that the latest (rightmost) items are shown
            let currentIndex = Math.max(0, allLabels.length - WINDOW_SIZE);

            const chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: allLabels.slice(currentIndex, currentIndex + WINDOW_SIZE),
                    datasets: [{
                        label: labelStr,
                        data: allValues.slice(currentIndex, currentIndex + WINDOW_SIZE),
                        borderColor: borderColor,
                        backgroundColor: bgColor,
                        borderWidth: 2,
                        pointBackgroundColor: pointColor,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            function updateChart() {
                chart.data.labels = allLabels.slice(currentIndex, currentIndex + WINDOW_SIZE);
                chart.data.datasets[0].data = allValues.slice(currentIndex, currentIndex + WINDOW_SIZE);
                chart.update();
                
                if (prevBtn) prevBtn.disabled = currentIndex <= 0;
                if (nextBtn) nextBtn.disabled = currentIndex + WINDOW_SIZE >= allLabels.length;
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    currentIndex = Math.max(0, currentIndex - WINDOW_SIZE);
                    updateChart();
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    currentIndex = Math.min(allLabels.length - WINDOW_SIZE, currentIndex + WINDOW_SIZE);
                    updateChart();
                });
            }
            updateChart(); // Set initial button states
        }

        initPaginatedChart('contestChart', 'c1-prev', 'c1-next', 'Contest Rating', '#00f0ff', 'rgba(0, 240, 255, 0.1)', '#ff007f');
        initPaginatedChart('solvedChart', 'c2-prev', 'c2-next', 'Difficulty Level', '#facc15', 'rgba(250, 204, 21, 0.1)', '#ffffff');

        // Modal Chart Logic
        let modalChartInstance = null;
        let modalCurrentIndex = 0;
        let modalWindowSize = 80; // Show more data points in the expanded view

        window.openChartModal = function(sourceCanvasId, title, borderColor, bgColor, pointColor) {
            const sourceCanvas = document.getElementById(sourceCanvasId);
            const modalCanvas = document.getElementById('modalChartCanvas');
            const modal = document.getElementById('chartModal');
            const modalTitle = document.getElementById('chartModalTitle');
            let prevBtn = document.getElementById('modal-prev');
            let nextBtn = document.getElementById('modal-next');

            if (!sourceCanvas || !modalCanvas || !modal) return;

            modalTitle.innerText = title;
            modal.style.display = "flex";
            document.body.style.overflow = "hidden"; // Prevent background scrolling

            const allLabels = JSON.parse(sourceCanvas.getAttribute('data-labels') || '[]');
            const allValues = JSON.parse(sourceCanvas.getAttribute('data-values') || '[]');

            modalCurrentIndex = Math.max(0, allLabels.length - modalWindowSize);

            if (modalChartInstance) {
                modalChartInstance.destroy();
            }

            modalChartInstance = new Chart(modalCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: allLabels.slice(modalCurrentIndex, modalCurrentIndex + modalWindowSize),
                    datasets: [{
                        label: title,
                        data: allValues.slice(modalCurrentIndex, modalCurrentIndex + modalWindowSize),
                        borderColor: borderColor,
                        backgroundColor: bgColor,
                        borderWidth: 2,
                        pointBackgroundColor: pointColor,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            function updateModalChart() {
                modalChartInstance.data.labels = allLabels.slice(modalCurrentIndex, modalCurrentIndex + modalWindowSize);
                modalChartInstance.data.datasets[0].data = allValues.slice(modalCurrentIndex, modalCurrentIndex + modalWindowSize);
                modalChartInstance.update();
                
                if (prevBtn) prevBtn.disabled = modalCurrentIndex <= 0;
                if (nextBtn) nextBtn.disabled = modalCurrentIndex + modalWindowSize >= allLabels.length;
            }

            // Remove old event listeners by cloning the buttons
            const newPrev = prevBtn.cloneNode(true);
            const newNext = nextBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrev, prevBtn);
            nextBtn.parentNode.replaceChild(newNext, nextBtn);
            
            prevBtn = newPrev;
            nextBtn = newNext;

            prevBtn.addEventListener('click', () => {
                modalCurrentIndex = Math.max(0, modalCurrentIndex - modalWindowSize);
                updateModalChart();
            });
            
            nextBtn.addEventListener('click', () => {
                modalCurrentIndex = Math.min(allLabels.length - modalWindowSize, modalCurrentIndex + modalWindowSize);
                updateModalChart();
            });
            
            updateModalChart();
        };

        const closeChartModalBtn = document.getElementById('closeChartModal');
        const chartModal = document.getElementById('chartModal');
        if (closeChartModalBtn && chartModal) {
            closeChartModalBtn.addEventListener('click', () => {
                chartModal.style.display = "none";
                document.body.style.overflow = "auto";
            });
            window.addEventListener('click', (event) => {
                if (event.target === chartModal) {
                    chartModal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            });
        }
    }

    // 6. Auto-fetch and platform detection logic
    const ratingGuides = {
        '1': 'Codeforces rating scale: e.g., 800 (Newbie) to 3500 (Grandmaster)',
        '2': 'LeetCode equivalent: Easy = 800, Medium = 1200, Hard = 1600',
        '3': 'Other/External: Enter any comparable difficulty rating (default: 1000)',
        '4': 'AtCoder rating scale: e.g., 100 (Beginner) to 4000 (Grandmaster)',
        '5': 'CodeChef rating scale: e.g., 1000 to 3000',
        '6': 'CSES difficulty rating: e.g., 1000 to 2500',
        '7': 'SPOJ typical rating scale: e.g., 1000 to 2500',
        '8': 'HackerRank typical rating: e.g., 1000 to 2500',
        '9': 'Topcoder rating scale: e.g., 1000 to 3000'
    };

    function detectPlatform(url) {
        if (!url) return '';
        const lower = url.toLowerCase();
        if (lower.includes('codeforces.com')) return '1';
        if (lower.includes('leetcode.com')) return '2';
        if (lower.includes('atcoder.jp')) return '4';
        if (lower.includes('codechef.com')) return '5';
        if (lower.includes('cses.fi')) return '6';
        if (lower.includes('spoj.com')) return '7';
        if (lower.includes('hackerrank.com')) return '8';
        if (lower.includes('topcoder.com')) return '9';
        return '3';
    }

    function setupAutofetch(urlInput, titleInput, platformSelect, ratingGuide, titleFeedback, submitBtn) {
        if (!urlInput) return;

        function updateRatingGuide() {
            const val = platformSelect.value;
            if (ratingGuides[val]) {
                ratingGuide.textContent = ratingGuides[val];
                ratingGuide.classList.add('active-info');
            } else {
                ratingGuide.textContent = 'Select a platform to view rating guidelines.';
                ratingGuide.classList.remove('active-info');
            }
        }

        if (platformSelect) {
            platformSelect.addEventListener('change', updateRatingGuide);
        }

        urlInput.addEventListener('input', function() {
            const detected = detectPlatform(this.value);
            if (detected && platformSelect && platformSelect.value !== detected) {
                platformSelect.value = detected;
                updateRatingGuide();
            }
        });

        urlInput.addEventListener('blur', function() {
            const url = this.value;
            if (url && titleInput && !titleInput.value) {
                titleInput.placeholder = "Fetching title automatically...";
                titleInput.classList.add('fetching-glow');
                if (titleFeedback) {
                    titleFeedback.innerHTML = '<span class="spinner-loader"></span> Fetching details...';
                    titleFeedback.style.color = '#facc15';
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                }

                fetch('includes/fetch_title.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                })
                .then(res => res.json())
                .then(data => {
                    titleInput.classList.remove('fetching-glow');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }

                    if (data.success) {
                        if (!titleInput.value) {
                            titleInput.value = data.title;
                        }
                        const ratingInput = document.getElementById(urlInput.id === 'modalUrlInput' ? 'modalRatingInput' : 'ratingInput');
                        if (data.rating && ratingInput) {
                            ratingInput.value = data.rating;
                        }
                        titleInput.classList.add('fetch-success-glow');
                        if (titleFeedback) {
                            titleFeedback.textContent = 'Autofill successful!';
                            titleFeedback.style.color = '#2ecc71';
                        }
                        setTimeout(() => {
                            titleInput.classList.remove('fetch-success-glow');
                        }, 1000);
                    } else if (titleFeedback) {
                        titleInput.placeholder = "Problem Title";
                        titleFeedback.textContent = 'Could not autofill. Please enter manually.';
                        titleFeedback.style.color = '#ec4899';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    titleInput.classList.remove('fetching-glow');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }
                    titleInput.placeholder = "Problem Title";
                    if (titleFeedback) {
                        titleFeedback.textContent = 'Error during fetch. Please write manually.';
                        titleFeedback.style.color = '#ec4899';
                    }
                });
            }
        });
    }

    // Bind autofetch to form inputs
    setupAutofetch(
        document.getElementById('modalUrlInput'),
        document.getElementById('modalTitleInput'),
        document.getElementById('modalPlatformSelect'),
        document.getElementById('modalRatingGuide'),
        document.getElementById('modalTitleFeedback'),
        document.getElementById('modalSubmitBtn')
    );

    setupAutofetch(
        document.getElementById('urlInput'),
        document.getElementById('titleInput'),
        document.getElementById('platformSelect'),
        document.getElementById('ratingGuide'),
        document.getElementById('titleFeedback'),
        document.getElementById('submitBtn')
    );

    // 7. Keyboard navigation for pagination using ArrowLeft and ArrowRight keys
    document.addEventListener("keydown", function(event) {
        // Only trigger if focus is NOT on any input, textarea, or select elements
        const active = document.activeElement;
        if (active && (
            active.tagName === 'INPUT' || 
            active.tagName === 'TEXTAREA' || 
            active.tagName === 'SELECT' || 
            active.isContentEditable
        )) {
            return;
        }

        if (event.key === "ArrowLeft") {
            const prevBtn = document.getElementById("pagination-prev");
            if (prevBtn) {
                prevBtn.click();
            }
        } else if (event.key === "ArrowRight") {
            const nextBtn = document.getElementById("pagination-next");
            if (nextBtn) {
                nextBtn.click();
            }
        }
    });
});