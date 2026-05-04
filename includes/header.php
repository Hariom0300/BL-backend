<?php
// Dongare — Shared Header Component
$cartCount = getCartCount();
$wishlistCount = isLoggedIn() ? getWishlistCount($_SESSION['user_id']) : 0;
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' — ' : ''; ?><?php echo SITE_NAME; ?> | <?php echo SITE_TAGLINE; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Dongare — Premium fashion for the modern individual. Discover curated collections of clothing, accessories & footwear.'; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Announcement Bar -->
    <div class="announcement-bar">
        <p>Free shipping on orders above ₹2,000 | Use code <strong>FIRST20</strong> for 20% off your first order</p>
    </div>

    <!-- Header -->
    <header class="site-header" id="siteHeader">
        <div class="container">
            <nav class="nav">
                <!-- Mobile Menu Toggle -->
                <button class="nav__hamburger" id="menuToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>

                <!-- Logo -->
                <a href="index.php" class="nav__logo">DONGARE</a>

                <!-- Desktop Navigation -->
                <ul class="nav__links" id="navLinks">
                    <li><a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="products.php" class="<?php echo $currentPage === 'products.php' ? 'active' : ''; ?>">Shop</a></li>
                    <li><a href="products.php?new=1" class="<?php echo isset($_GET['new']) ? 'active' : ''; ?>">New Arrivals</a></li>
                    <li><a href="about.php" class="<?php echo $currentPage === 'about.php' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="contact.php" class="<?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
                </ul>

                <!-- Right Actions -->
                <div class="nav__actions">
                    <!-- Search -->
                    <button class="nav__icon-btn" id="searchToggle" aria-label="Search">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </button>

                    <!-- User -->
                    <?php if (isLoggedIn()): ?>
                        <a href="account.php" class="nav__icon-btn" aria-label="Account">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="nav__icon-btn" aria-label="Login">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist -->
                    <a href="<?php echo isLoggedIn() ? 'account.php#wishlist' : 'login.php'; ?>" class="nav__icon-btn nav__icon-btn--wishlist" aria-label="Wishlist">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        <?php if ($wishlistCount > 0): ?>
                            <span class="nav__badge"><?php echo $wishlistCount; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Cart -->
                    <a href="cart.php" class="nav__icon-btn nav__icon-btn--cart" aria-label="Cart">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span class="nav__badge cart-count" style="display:<?php echo $cartCount > 0 ? 'flex' : 'none'; ?>"><?php echo $cartCount; ?></span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Search Overlay -->
        <div class="search-overlay" id="searchOverlay">
            <div class="container">
                <div class="search-overlay__inner">
                    <input type="text" class="search-overlay__input" id="searchInput" placeholder="Search for products..." autocomplete="off">
                    <button class="search-overlay__close" id="searchClose">✕</button>
                </div>
                <div class="search-overlay__results" id="searchResults"></div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <main>
