    <!-- section header do not modify -->
    <header>
      <nav class="navbar">
        <div class="logo">
          <a href="/"><img
              src="<?= DOMAIN . HOME_URL . 'assets/imgs/logo.jpg' ?>"
              alt="Logo"
              width="60"
              height="60" />
          </a>
          <p class="logo_paragraph">LOGO</p>
        </div>
        <ul class="nav-links">
          <li><a class="link" href="<?= HOME_URL  ?>">LINK1</a></li>
          <?php if (isset($_SESSION['connected'])) : ?>
            <li>
              <a class="btn linkNotDecorated " href="<?= HOME_URL . 'URI' ?>">LINK3</a>
            </li>
          <?php else : ?>

            <li>
              <a class="btn linkNotDecorated" href="<?= HOME_URL . 'URI' ?>">LINK2</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    </header>