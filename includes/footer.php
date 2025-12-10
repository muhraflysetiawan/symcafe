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
            
            // Sidebar behavior: minimized by default, expand on hover, lock on click, minimize on mouseleave
            // NO DELAYS - instant response
            if (sidebar) {
                const mainContent = document.querySelector('.main-content');
                let sidebarLocked = false;
                let lockedWidth = null;
                
                // Function to lock sidebar at current width
                function lockSidebarAtCurrentWidth() {
                    sidebarLocked = true;
                    // Get current width (could be hover width or collapsed width)
                    lockedWidth = sidebar.offsetWidth;
                    sidebar.classList.add('locked');
                    sidebar.style.width = lockedWidth + 'px';
                    // Update main content margin to match
                    if (mainContent) {
                        mainContent.style.marginLeft = lockedWidth + 'px';
                    }
                }
                
                // Function to unlock and collapse sidebar
                function unlockAndCollapseSidebar() {
                    sidebarLocked = false;
                    lockedWidth = null;
                    sidebar.classList.remove('locked');
                    sidebar.classList.add('collapsed');
                    sidebar.style.width = '';
                    if (mainContent) {
                        mainContent.style.marginLeft = '';
                    }
                }
                
                // When nav links are clicked, lock sidebar at current width
                const navLinks = document.querySelectorAll('.sidebar .nav-link');
                navLinks.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        // Lock at current width when clicked
                        lockSidebarAtCurrentWidth();
                        // Mark that sidebar should collapse after navigation
                        sessionStorage.setItem('sidebarShouldCollapse', 'true');
                    });
                });
                
                // When mouse leaves sidebar, unlock and collapse IMMEDIATELY
                sidebar.addEventListener('mouseleave', function(e) {
                    if (sidebarLocked) {
                        unlockAndCollapseSidebar();
                    } else {
                        // If not locked, just collapse immediately
                        sidebar.classList.add('collapsed');
                    }
                });
                
                // When mouse enters, remove collapse flag
                sidebar.addEventListener('mouseenter', function(e) {
                    // Remove collapse flag if mouse re-enters
                    sessionStorage.removeItem('sidebarShouldCollapse');
                });
                
                // On page load, check if sidebar should be collapsed after navigation - IMMEDIATE, NO DELAY
                const shouldCollapse = sessionStorage.getItem('sidebarShouldCollapse');
                if (shouldCollapse === 'true') {
                    // Check immediately - no delay at all
                    let mouseCheckDone = false;
                    
                    // Check mouse position on next movement
                    const checkMousePosition = function(e) {
                        if (mouseCheckDone) return;
                        mouseCheckDone = true;
                        
                        const rect = sidebar.getBoundingClientRect();
                        const isOverSidebar = (
                            e.clientX >= rect.left && 
                            e.clientX <= rect.right &&
                            e.clientY >= rect.top && 
                            e.clientY <= rect.bottom
                        );
                        
                        // If mouse is not over sidebar, collapse it IMMEDIATELY
                        if (!isOverSidebar) {
                            unlockAndCollapseSidebar();
                        }
                        
                        sessionStorage.removeItem('sidebarShouldCollapse');
                        document.removeEventListener('mousemove', checkMousePosition);
                    };
                    
                    // Listen for next mouse movement - immediate
                    document.addEventListener('mousemove', checkMousePosition, { once: true });
                    
                    // Also try to collapse immediately if mouse is already away (no movement needed)
                    // Use requestAnimationFrame for immediate check without blocking
                    requestAnimationFrame(function() {
                        // If no mouse movement detected yet, assume mouse is not over sidebar and collapse
                        if (!mouseCheckDone) {
                            unlockAndCollapseSidebar();
                            sessionStorage.removeItem('sidebarShouldCollapse');
                        }
                    });
                }
                
                // Also check if sidebar has locked state from DOM (backup check) - IMMEDIATE
                const hasLockedClass = sidebar.classList.contains('locked');
                const hasLockedWidth = sidebar.style.width && sidebar.style.width !== '';
                
                if ((hasLockedClass || hasLockedWidth) && !sidebarLocked) {
                    // Sidebar is locked but JavaScript state is reset, unlock it immediately
                    unlockAndCollapseSidebar();
                }
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

