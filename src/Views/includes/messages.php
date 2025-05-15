<?php 
// these error in singular for one message in case of we have only one message of success or error 
if (isset($_SESSION['error'])): ?>
    <!-- Alert Messages -->
    <div class="custom-alert custom-alert-danger">
        <p>
            <?= htmlspecialchars($_SESSION['error']); ?>
        </p>
        <button class="custom-btn-close" onclick="this.parentElement.remove()" aria-label="Close">❌</button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="custom-alert custom-alert-success">
        <p>
            <?= htmlspecialchars($_SESSION['success']); ?>
        </p>
        <button class="custom-btn-close" onclick="this.parentElement.remove()" aria-label="Close">❌</button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php
// display all errors when making ctrl on submit button so all the cases of if withe the results errors should be stocked at the last verification of the form data and it display by throw and exception to allow the code to jump to catch case so we can get the errors .
if (!empty($_SESSION['errors'])): ?>
    <ul class="error-list">
        <?php foreach ($_SESSION['errors'] as $key => $error): ?>
            <li class="error-item">
                <?= htmlspecialchars($error) ?>
                <button class="custom-btn-close" onclick="
                    const li = this.parentElement;
                    const ul = li.parentElement;
                    li.remove();
                    if (ul.children.length === 0) {
                        ul.remove();
                    }
                " aria-label="Close">❌</button>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php unset($_SESSION['errors']); ?>
<?php endif; ?>