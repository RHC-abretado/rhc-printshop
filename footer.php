    <!-- End of main content area -->
  </div> <!-- /container -->
</div> <!-- /content -->
</main>
<!-- Footer Section -->
<footer class="bg-dark text-white text-center py-3">
  <p class="mb-0">&copy; Copyright <?php echo date('Y'); ?> - <a href="https://www.deviant.media" target="_blank" >Deviant Media LLC</a>. All Rights Reserved. <br>Version 2.2 | <a href="changelog.php">View Changelog</a><?php if (empty($_SESSION['logged_in'])): ?>
      | <a href="login.php">Admin Login</a>
    <?php endif; ?></p>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
