<?php
session_start();
require_once(__DIR__ . '/api/config/db_config.php');

// Fetch approved feedback with customer names (using new customer_feedback table)
$sql = "SELECT 
            cf.FeedbackID,
            cf.CustomerID,
            CASE 
                WHEN cf.IsAnonymous = 1 THEN 'Anonymous'
                ELSE CONCAT(c.FirstName, ' ', LEFT(c.LastName, 1), '.')
            END AS DisplayName,
            cf.FeedbackType,
            cf.OrderID,
            cf.ReservationID,
            cf.OverallRating,
            cf.FoodTasteRating,
            cf.PortionSizeRating,
            cf.ServiceRating,
            cf.AmbienceRating,
            cf.CleanlinessRating,
            cf.FoodTasteComment,
            cf.PortionSizeComment,
            cf.ServiceComment,
            cf.AmbienceComment,
            cf.CleanlinessComment,
            cf.ReviewMessage,
            cf.CreatedDate
        FROM customer_feedback cf
        JOIN customers c ON cf.CustomerID = c.CustomerID
        WHERE cf.Status = 'Approved'
        ORDER BY cf.CreatedDate DESC";

$reviews_result = $conn->query($sql);

// Get statistics from customer_feedback table
$stats_sql = "SELECT 
    COUNT(*) as total,
    COALESCE(ROUND(AVG(OverallRating), 1), 0) as avg_rating,
    COALESCE(ROUND(AVG(FoodTasteRating), 1), 0) as avg_food,
    COALESCE(ROUND(AVG(PortionSizeRating), 1), 0) as avg_portion,
    COALESCE(ROUND(AVG(ServiceRating), 1), 0) as avg_service,
    COALESCE(ROUND(AVG(AmbienceRating), 1), 0) as avg_ambience,
    COALESCE(ROUND(AVG(CleanlinessRating), 1), 0) as avg_cleanliness,
    SUM(CASE WHEN OverallRating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN OverallRating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN OverallRating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN OverallRating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN OverallRating = 1 THEN 1 ELSE 0 END) as one_star
FROM customer_feedback WHERE Status = 'Approved'";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<?php
// Helper Function for Initials
function getInitials($name) {
    if (!$name || $name === 'Anonymous') return 'A';
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    return substr($initials, 0, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Tabeya</title>
    <link rel="stylesheet" href="CSS/ReviewDesign.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="Photo/Tabeya Name.png" alt="Logo">
        </div>
        <nav class="nav-links">
            <a href="Home.html">HOME</a>
            <a href="Menu.html">MENU</a>
            <a href="CaterReservation.html">CATER RESERVATION</a>
            <a href="Review.php" class="active">TESTIMONY</a>
            <a href="About.html">ABOUT</a>
        </nav>
        <div class="header-right">
            <a href="Login.html" id="account-link">PROFILE</a>
            <div class="cart-icon" id="view-cart-btn" role="button" tabindex="0">
                🛒 <span class="cart-count" id="cart-item-count">0</span>
            </div>
        </div>
    </header>

    <div class="testimony-container">
        <div class="testimony-layout">
            <!-- Left Sidebar -->
            <aside class="sidebar">
                <div class="page-titles">
                    <h1>Customer Reviews</h1>
                    <p>See what our customers are saying about their dining experience.</p>
                </div>

                <div class="stats-card overall-card">
                    <h3>Overall Rating</h3>
                    <div class="huge-rating"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                    <div class="main-stars">
                        <?php
                        $rating = floatval($stats['avg_rating']);
                        for ($i = 1; $i <= 5; $i++) {
                            $filled = ($rating >= $i) ? 'filled' : '';
                            echo '<span class="star ' . $filled . '">★</span>';
                        }
                        ?>
                    </div>
                    <p class="based-on">Based on <?php echo $stats['total']; ?> Reviews</p>
                    
                    <div class="rating-bars">
                        <?php
                        $total = max(intval($stats['total']), 1);
                        $stars = [5 => 'five', 4 => 'four', 3 => 'three', 2 => 'two', 1 => 'one'];
                        foreach ($stars as $num => $name) {
                            $count = intval($stats[$name . '_star']) ?: 0;
                            $percent = ($count / $total) * 100;
                            echo '<div class="rating-bar-row">
                                    <span class="star-label">' . $num . ' Stars</span>
                                    <div class="bar-bg"><div class="bar-fill" style="width: ' . $percent . '%"></div></div>
                                    <span class="count-label">' . $count . '</span>
                                  </div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="stats-card categories-card">
                    <h3>Category Ratings</h3>
                    <?php
                    $categories = [
                        'Food Taste' => $stats['avg_food'],
                        'Portion Size' => $stats['avg_portion'],
                        'Service' => $stats['avg_service'],
                        'Ambience' => $stats['avg_ambience'],
                        'Cleanliness' => $stats['avg_cleanliness']
                    ];
                    foreach ($categories as $label => $value) {
                        $val = floatval($value) ?: 0;
                        $percent = ($val / 5) * 100;
                        echo '<div class="category-row">
                                <span class="cat-name">' . $label . '</span>
                                <div class="cat-bar-bg"><div class="cat-bar-fill" style="width: ' . $percent . '%"></div></div>
                                <span class="cat-score">' . number_format($val, 1) . '</span>
                              </div>';
                    }
                    ?>
                </div>

                <button id="write-review-btn" class="write-btn">Write a Review</button>
            </aside>

            <!-- Main Content Area -->
            <main class="main-content">
                <div class="content-header">
                    <h2>Recent Reviews</h2>
                    <select class="sort-select">
                        <option value="recent">Recent</option>
                        <option value="oldest">Oldest</option>
                    </select>
                </div>

                <div class="reviews-list" id="reviews-container">
                    <?php 
                    if ($reviews_result && $reviews_result->num_rows > 0) {
                        while ($review = $reviews_result->fetch_assoc()) {
                            $initials = getInitials($review['DisplayName']);
                            $dateAttr = date('Y-m-d H:i:s', strtotime($review['CreatedDate']));
                            echo '<div class="review-card" data-date="' . $dateAttr . '">';
                            
                            // User Header
                            echo '<div class="review-card-header">';
                            echo '<div class="avatar">' . htmlspecialchars($initials) . '</div>';
                            echo '<div class="user-meta">';
                            echo '<div class="user-top">';
                            echo '<span class="user-name">' . htmlspecialchars($review['DisplayName']) . '</span>';
                            echo '<span class="type-badge">' . $review['FeedbackType'] . '</span>';
                            echo '</div>';
                            
                            // Date
                            echo '<div class="review-date">' . date('M d, Y', strtotime($review['CreatedDate'])) . '</div>';
                            
                            // Stars
                            echo '<div class="card-main-stars">';
                            for ($i = 1; $i <= 5; $i++) {
                                $filled = ($i <= $review['OverallRating']) ? 'filled' : '';
                                echo '<span class="star ' . $filled . '">★</span>';
                            }
                            echo '</div>';
                            echo '</div></div>'; // end user-meta, review-card-header

                            // Category Badges
                            echo '<div class="category-badges">';
                            $cats = [
                                'Food' => $review['FoodTasteRating'],
                                'Portion' => $review['PortionSizeRating'],
                                'Service' => $review['ServiceRating']
                            ];
                            foreach ($cats as $label => $r) {
                                if ($r > 0) {
                                    echo '<div class="cat-badge">' . $label . ' <span class="mini-stars">' . str_repeat('★', $r) . '</span></div>';
                                }
                            }
                            echo '</div>';

                            // Message
                            if ($review['ReviewMessage']) {
                                echo '<p class="review-text">"' . htmlspecialchars($review['ReviewMessage']) . '"</p>';
                            }

                            // Accordion
                            $hasComments = $review['FoodTasteComment'] || $review['PortionSizeComment'] || 
                                          $review['ServiceComment'] || $review['AmbienceComment'] || 
                                          $review['CleanlinessComment'];
                            
                            if ($hasComments) {
                                echo '<button class="accordion-btn" onclick="toggleAccordion(this)">View detailed feedback ⌵</button>';
                                echo '<div class="accordion-content">';
                                
                                $comments = [
                                    'Food Taste' => $review['FoodTasteComment'],
                                    'Portion Size' => $review['PortionSizeComment'],
                                    'Customer Service' => $review['ServiceComment'],
                                    'Ambience' => $review['AmbienceComment'],
                                    'Cleanliness' => $review['CleanlinessComment']
                                ];
                                
                                foreach ($comments as $label => $comment) {
                                    if ($comment) {
                                        echo '<div class="comment-group">';
                                        echo '<div class="comment-label">' . $label . '</div>';
                                        echo '<div class="comment-text">' . htmlspecialchars($comment) . '</div>';
                                        echo '</div>';
                                    }
                                }
                                echo '</div>'; // end accordion-content
                            }
                            
                            echo '</div>'; // end review-card
                        }
                    } else {
                        echo '<p style="text-align: center; color: #666; margin-top: 50px;">No reviews yet. Be the first to share your experience!</p>';
                    }
                    ?>
                </div>

                <div class="load-more">
                    <button class="load-more-btn">Load more reviews</button>
                </div>
            </main>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="review-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="close-modal-btn">&times;</span>
            <h2 style="margin-bottom: 20px; color: #bc1823;">Share Your Experience</h2>
            
            <div id="user-info-section"></div>
            
            <div class="feedback-type-selection">
                <h4>What would you like to review?</h4>
                <div class="feedback-type-buttons">
                    <button class="feedback-type-btn" data-type="Order">📦 Recent Order</button>
                    <button class="feedback-type-btn" data-type="Reservation">🎉 Recent Reservation</button>
                    <button class="feedback-type-btn active" data-type="General">⭐ General Experience</button>
                </div>
            </div>
            
            <div class="reviewable-items-section" id="orders-section">
                <h4>Select an order to review:</h4>
                <div id="orders-list"></div>
            </div>
            
            <div class="reviewable-items-section" id="reservations-section">
                <h4>Select a reservation to review:</h4>
                <div id="reservations-list"></div>
            </div>
            
            <div class="anonymous-option" style="margin: 15px 0; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="anonymous-checkbox">
                <label for="anonymous-checkbox" style="font-weight: 600; font-size: 14px; cursor: pointer;">Post anonymously</label>
            </div>
            
            <div class="overall-rating-input" style="text-align: center; padding: 20px; background: linear-gradient(135deg, #bc1823 0%, #e74c3c 100%); border-radius: 10px; margin-bottom: 20px; color: white;">
                <h3>Overall Rating</h3>
                <div class="rating-stars" id="overall-stars" style="justify-content: center; display: flex; gap: 5px;">
                    <span class="rating-star" data-rating="1">★</span>
                    <span class="rating-star" data-rating="2">★</span>
                    <span class="rating-star" data-rating="3">★</span>
                    <span class="rating-star" data-rating="4">★</span>
                    <span class="rating-star" data-rating="5">★</span>
                </div>
            </div>

            <div class="review-form-section">
                <h4 style="color: #bc1823; margin-bottom: 15px;">Rate Each Category</h4>
                
                <div class="rating-category">
                    <label style="min-width: 150px; font-weight: 600;">🍽️ Food Taste</label>
                    <div class="category-stars" data-category="food">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="food-comment" placeholder="Tell us about the food..."></textarea>

                <div class="rating-category">
                    <label style="min-width: 150px; font-weight: 600;">📏 Portion Size</label>
                    <div class="category-stars" data-category="portion">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="portion-comment" placeholder="Was the portion satisfying?"></textarea>

                <div class="rating-category">
                    <label style="min-width: 150px; font-weight: 600;">👨‍💼 Customer Service</label>
                    <div class="category-stars" data-category="service">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="service-comment" placeholder="How was the service?"></textarea>

                <div class="rating-category">
                    <label style="min-width: 150px; font-weight: 600;">✨ Ambience</label>
                    <div class="category-stars" data-category="ambience">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="ambience-comment" placeholder="Describe the atmosphere..."></textarea>

                <div class="rating-category">
                    <label style="min-width: 150px; font-weight: 600;">🧹 Cleanliness</label>
                    <div class="category-stars" data-category="cleanliness">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                </div>
                <textarea class="comment-field" id="cleanliness-comment" placeholder="How clean was the place?"></textarea>
            </div>

            <div class="review-form-section">
                <h4 style="color: #bc1823; margin-top: 15px;">General Comments (Optional)</h4>
                <textarea class="comment-field" id="review-message" placeholder="Share your overall experience..." style="min-height: 80px;"></textarea>
            </div>

            <button id="submit-review-btn" class="write-btn">Submit Review</button>
        </div>
    </div>

    <!-- Cart Modal -->
    <div id="cart-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-btn" onclick="document.getElementById('cart-modal').style.display='none'">&times;</span>
            <h2 style="margin-bottom: 20px; color: #bc1823;">Your Cart</h2>
            <div id="cart-items-list"></div>
            <div class="cart-summary" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; text-align: right;">
                <strong>Total: ₱<span id="cart-total">0.00</span></strong>
            </div>
            <button id="checkout-btn" class="write-btn" style="margin-top: 20px;">Proceed to Checkout</button>
        </div>
    </div>

    <footer>
        <div class="contact-section" style="padding: 50px 0; background: #fff; border-top: 1px solid #eee; margin-top: 50px;">
            <div class="contact-info" style="text-align: center; max-width: 800px; margin: 0 auto;">
                <h2 style="color: #bc1823; margin-bottom: 10px;">Contact Us</h2>
                <p style="color: #666; margin-bottom: 30px;">Have any questions? We'd love to hear from you.</p>
                <div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px; text-align: left;">
                        <img src="Photo/VisitUs.png" alt="Location" style="width: 30px;">
                        <div><strong>Visit us</strong><p style="font-size: 13px; line-height: 1.4;">Poblacion 2, Vinzons Avenue,<br>Vinzons, CN</p></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; text-align: left;">
                        <img src="Photo/Selpon.png" alt="Phone" style="width: 30px;">
                        <div><strong>Call us</strong><p style="font-size: 13px;">09380839641</p></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; text-align: left;">
                        <img src="Photo/Facebook.png" alt="Facebook" style="width: 30px;">
                        <div><strong>Connect</strong><p style="font-size: 13px;"><a href="#" style="color: #bc1823; text-decoration: none;">Tabeya, VCN</a></p></div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="review_enhanced.js"></script>
</body>
</html>
<?php $conn->close(); ?>