    <!-- section header do not modify -->
    <header>
      <nav class="navbar bg-grey d-flex align-items-center justify-content-between px-3">
        <div class="logo">
          <a href="/"><img
              src="<?= \App\Services\Config::baseUrl() . '/assets/imgs/logo.jpg' ?>"
              alt="Logo"
              width="60"
              height="60" />
          </a>
        </div>
        <ul class="nav-links d-flex align-items-center gap-3 ">
          <li><a class="link" href="<?= \App\Services\Config::baseUrl() ?>/">LINK1</a></li>
          <?php if (isset($_SESSION['connected'])) : ?>
            <li>
              <a class="btn linkNotDecorated " href="<?= \App\Services\Config::baseUrl() . '/dashboard' ?>">Dashboard</a>
            </li>
          <?php else : ?>

            <li>
              <a class="btn btn-primary" href="<?= \App\Services\Config::baseUrl() . '/login' ?>">Login</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    </header>