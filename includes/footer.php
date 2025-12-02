            </div>
        </div>
    </div>
    <script>
        // Sidebar hover functionality (default collapsed, expand on hover)
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            
            // Ensure sidebar starts collapsed
            if (sidebar) {
                sidebar.classList.add('collapsed');
            }
            
            // Prevent sidebar expansion on click and maintain width until mouseleave
            if (sidebar) {
                const mainContent = document.querySelector('.main-content');
                let sidebarClicked = false;
                
                // Function to lock sidebar width
                function lockSidebarWidth() {
                    sidebarClicked = true;
                    const currentWidth = sidebar.offsetWidth;
                    sidebar.classList.add('clicked');
                    sidebar.style.width = currentWidth + 'px';
                    sidebar.setAttribute('data-locked-width', currentWidth);
                    // Update main content margin to match
                    if (mainContent) {
                        mainContent.style.marginLeft = currentWidth + 'px';
                    }
                }
                
                // Function to unlock sidebar width
                function unlockSidebarWidth() {
                    sidebarClicked = false;
                    sidebar.classList.remove('clicked');
                    sidebar.style.width = '';
                    sidebar.removeAttribute('data-locked-width');
                    if (mainContent) {
                        mainContent.style.marginLeft = '';
                    }
                }
                
                // When sidebar is clicked anywhere, lock its current width
                sidebar.addEventListener('click', function(e) {
                    lockSidebarWidth();
                });
                
                // When nav links are clicked, prevent hover expansion
                const navLinks = document.querySelectorAll('.sidebar .nav-link');
                navLinks.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        lockSidebarWidth();
                    });
                });
                
                // When mouse leaves sidebar, unlock and collapse
                sidebar.addEventListener('mouseleave', function(e) {
                    unlockSidebarWidth();
                    sidebar.classList.add('collapsed');
                });
                
                // When mouse enters, allow hover expansion only if not clicked
                sidebar.addEventListener('mouseenter', function(e) {
                    if (!sidebarClicked) {
                        unlockSidebarWidth();
                    }
                });
            }
            
            // Mobile menu toggle
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            if (mobileMenuBtn && sidebar) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }
            
            // Real-time clock - optimized: cache date strings, only update time
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const timeElement = document.getElementById('clockTime');
            const dateElement = document.getElementById('clockDate');
            
            // Set date once (only changes once per day)
            if (dateElement) {
                const now = new Date();
                const dayName = days[now.getDay()];
                const monthName = months[now.getMonth()];
                dateElement.textContent = `${dayName}, ${monthName} ${now.getDate()}, ${now.getFullYear()}`;
            }
            
            // Only update time (changes every second)
            function updateClock() {
                if (timeElement) {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    timeElement.textContent = `${hours}:${minutes}:${seconds}`;
                }
            }
            
            // Update clock immediately and then every second
            updateClock();
            setInterval(updateClock, 1000);
            
            // Make header more visible when scrolling - optimized with debounce
            const topNav = document.querySelector('.top-nav');
            let scrollTimeout;
            
            if (topNav) {
                window.addEventListener('scroll', function() {
                    // Debounce scroll events for better performance
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                        if (scrollTop > 50) {
                            topNav.classList.add('scrolled');
                        } else {
                            topNav.classList.remove('scrolled');
                        }
                    }, 10); // Small delay to batch scroll events
                }, { passive: true }); // Passive listener for better performance
            }
        });
    </script>
</body>
</html>

