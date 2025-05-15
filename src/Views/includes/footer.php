<footer class="custom-footer bg-dark text-white text-center mt-auto py-4">

  <hr>
  <p>footer</p>
  <?php
  $route = $_SERVER['REDIRECT_URL'];

  switch ($route) {
// TODO include the script upon the page you want to use it
    case HOME_URL . 'theURL':
      // echo '<script src="' . HOME_URL . 'assets/scripts/make_reservation.js' . '"></script>';
      break;
    default:
      break;
  }
  ?>
</footer>
</body>

</html>