<?php
/**
 * HTML Footer template
 * FoundU - Lost & Found Management System
 */
?>
        <!-- App Content Ends -->
        <?php if (is_logged_in()): ?>
            <footer class="app-footer py-3 mt-auto">
                <div class="container-fluid px-4 text-center text-md-start d-md-flex align-items-center justify-content-between">
                    <span class="text-muted small">&copy; 2026 FoundU Development Team. All rights reserved.</span>
                    <div class="footer-links mt-2 mt-md-0">
                        <span class="text-muted small">FoundU &middot; Lost &amp; Found Management System</span>
                    </div>
                </div>
            </footer>
        <?php endif; ?>
    </div> <!-- .app-wrapper ends -->

    <?php if (is_logged_in()): ?>
        <!-- Logout Confirmation Modal -->
        <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow rounded-4">
                    <div class="modal-header border-0 pb-1">
                        <h5 class="modal-title font-display fw-semibold text-dark d-flex align-items-center gap-2" id="logoutConfirmModalLabel">
                            <i class="fa-solid fa-right-from-bracket text-danger"></i>
                            <span>Confirm Logout</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-2">
                        <p class="small text-muted mb-0">Are you sure you want to log out of your FoundU operator session?</p>
                    </div>
                    <div class="modal-footer border-0 pt-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                        <a href="<?= base_url('logout.php') ?>" class="btn btn-danger btn-sm px-3" id="confirmLogoutLink">Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-Time Notification Toast Container -->
        <div id="notificationToastContainer" class="notification-toast-container" aria-live="polite" aria-atomic="true"></div>
    <?php endif; ?>

    <!-- Bootstrap 5 Bundle with Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Custom Vanilla JavaScript -->
    <script src="<?= base_url('assets/js/main.js') ?>"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $jsFile): ?>
            <script src="<?= base_url($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
