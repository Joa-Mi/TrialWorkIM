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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - Tabeya</title>
    <link rel="stylesheet" href="CSS/ReviewDesign.css">
    <style>
        .cart-icon {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: #333;
        }
        .cart-icon .cart-count {
            background: #bc1823;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 3% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-content .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Feedback Type Selection */
        .feedback-type-selection {
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .feedback-type-selection h4 {
            color: #bc1823;
            margin-bottom: 15px;
        }
        .feedback-type-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .feedback-type-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .feedback-type-btn:hover {
            border-color: #bc1823;
            background: #fff5f5;
        }
        .feedback-type-btn.active {
            border-color: #bc1823;
            background: #bc1823;
            color: white;
        }
        
        /* Reviewable Items Selection */
        .reviewable-items-section {
            display: none;
            margin: 15px 0;
        }
        .reviewable-items-section.show {
            display: block;
        }
        .reviewable-item {
            padding: 12px;
            margin: 8px 0;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .reviewable-item:hover {
            border-color: #bc1823;
            background: #fff5f5;
        }
        .reviewable-item.selected {
            border-color: #bc1823;
            background: #ffe8e8;
        }
        .reviewable-item.reviewed {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .item-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #4caf50;
            color: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .item-badge.reviewed {
            background: #999;
        }
        .item-products {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Anonymous Option */
        .anonymous-option {
            margin: 15px 0;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .anonymous-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .anonymous-option label {
            cursor: pointer;
            font-weight: 600;
            color: #333;
        }
        
        .review-form-section { margin-bottom: 20px; }
        .review-form-section h4 { color: #bc1823; margin-bottom: 10px; font-size: 14px; }
        .rating-category { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 8px; }
        .rating-category label { min-width: 120px; font-weight: 600; color: #333; font-size: 13px; }
        .category-stars { display: flex; gap: 5px; }
        .category-stars .star { font-size: 24px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .category-stars .star:hover, .category-stars .star.active { color: #FFD700; }
        .comment-field { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; resize: vertical; min-height: 60px; margin-top: 8px; }
        .overall-rating-input { text-align: center; padding: 15px; background: linear-gradient(135deg, #bc1823 0%, #e74c3c 100%); border-radius: 10px; margin-bottom: 20px; }
        .overall-rating-input h3 { color: white; margin-bottom: 10px; }
        .overall-rating-input .rating-stars { justify-content: center; }
        .overall-rating-input .rating-star { font-size: 36px; }
        .user-logged-in { background: #e8f5e9; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .user-logged-in .user-icon { font-size: 24px; }
        .user-logged-in .user-name { font-weight: 600; color: #2e7d32; }
        .login-required { background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center; }
        .login-required a { color: #bc1823; font-weight: 600; }
        
        .feedback-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        .feedback-type-badge.order {
            background: #2196F3;
            color: white;
        }
        .feedback-type-badge.reservation {
            background: #9C27B0;
            color: white;
        }
        .feedback-type-badge.general {
            background: #607D8B;
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="Photo/Tabeya Name.png" alt="Logo">
        </div>
        <nav>
            <a href="index.html">HOME</a>
            <a href="Menu.html">MENU</a>
            <a href="CaterReservation.html">CATER RESERVATION</a>
            <a href="GALLERY.html">GALLERY</a>
            <a href="Review.php" class="active">TESTIMONY</a>
            <a href="About.html">ABOUT</a>
            <a href="Login.html" id="account-link">PROFILE</a>
            <div class="cart-icon" id="view-cart-btn" role="button" tabindex="0">
                🛒 <span class="cart-count" id="cart-item-count">0</span>
            </div>
        </nav>
    </header>

    <section class="background">
        <img src="Photo/background.jpg" alt="Background">
        <div class="rectangle-panel">
            <div class="review-header">
                <h1>Customer Reviews</h1>
                <button id="write-review-btn" class="btn">Write a Review</button>
            </div>

            <div id="overall-rating-section" class="overall-rating">
                <h2 id="overall-rating-value"><?php echo $stats['avg_rating'] ?: '0.0'; ?></h2>
                <div class="star-rating" id="overall-star-rating">
                    <?php
                    $rating = floatval($stats['avg_rating']);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($rating >= $i) {
                            echo '<span class="star active">★</span>';
                        } elseif ($rating > $i - 1) {
                            echo '<span class="star active half-star">★</span>';
                        } else {
                            echo '<span class="star">★</span>';
                        }
                    }
                    ?>
                </div>
                <div class="total-reviews">
                    Based on <span id="total-reviews-count"><?php echo $stats['total'] ?: '0'; ?></span> Reviews
                </div>
            </div>

            <div id="review-progress-section" class="review-progress">
                <?php
                $total = max(intval($stats['total']), 1);
                $stars = [5 => 'five', 4 => 'four', 3 => 'three', 2 => 'two', 1 => 'one'];
                foreach ($stars as $num => $name) {
                    $count = intval($stats[$name . '_star']) ?: 0;
                    $percent = ($count / $total) * 100;
                    echo '<div class="progress-row">
                            <div class="progress-label">' . $num . ' Stars</div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: ' . $percent . '%"></div>
                            </div>
                          </div>';
                }
                ?>
            </div>

            <div class="category-breakdown">
                <h4>Category Ratings</h4>
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
                    echo '<div class="category-bar">
                            <span class="cat-label">' . $label . '</span>
                            <div class="cat-bar"><div class="cat-bar-fill" style="width: ' . $percent . '%"></div></div>
                            <span class="cat-value">' . number_format($val, 1) . '</span>
                          </div>';
                }
                ?>
            </div>

            <div id="reviews-container" class="user-reviews">
                <?php 
                if ($reviews_result && $reviews_result->num_rows > 0) {
                    while ($review = $reviews_result->fetch_assoc()) {
                        echo '<div class="user-review">';
                        echo '<div class="review-header">';
                        echo '<h3>' . htmlspecialchars($review['DisplayName']);
                        
                        // Show feedback type badge
                        $badgeClass = strtolower($review['FeedbackType']);
                        echo '<span class="feedback-type-badge ' . $badgeClass . '">' . $review['FeedbackType'] . '</span>';
                        
                        echo '</h3>';
                        echo '<div class="review-rating">';
                        for ($i = 1; $i <= 5; $i++) {
                            $active = ($i <= $review['OverallRating']) ? 'active' : '';
                            echo '<span class="star ' . $active . '">★</span>';
                        }
                        echo '</div></div>';
                        
                        echo '<div class="review-detail-ratings">';
                        $cats = [
                            '🍽️ Food' => $review['FoodTasteRating'],
                            '📏 Portion' => $review['PortionSizeRating'],
                            '👨‍💼 Service' => $review['ServiceRating'],
                            '✨ Ambience' => $review['AmbienceRating'],
                            '🧹 Clean' => $review['CleanlinessRating']
                        ];
                        foreach ($cats as $icon => $rating) {
                            if ($rating) {
                                echo '<span class="rating-badge">' . $icon . ' <span class="stars">' . str_repeat('★', $rating) . '</span></span>';
                            }
                        }
                        echo '</div>';
                        
                        if ($review['ReviewMessage']) {
                            echo '<p>' . htmlspecialchars($review['ReviewMessage']) . '</p>';
                        }
                        
                        $hasComments = $review['FoodTasteComment'] || $review['PortionSizeComment'] || 
                                      $review['ServiceComment'] || $review['AmbienceComment'] || 
                                      $review['CleanlinessComment'];
                        
                        if ($hasComments) {
                            echo '<div class="review-comments-accordion">';
                            echo '<button class="accordion-toggle" onclick="toggleAccordion(this)">View detailed feedback ▼</button>';
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
                                    echo '<div class="comment-item">';
                                    echo '<div class="comment-label">' . $label . '</div>';
                                    echo '<div class="comment-text">' . htmlspecialchars($comment) . '</div>';
                                    echo '</div>';
                                }
                            }
                            echo '</div></div>';
                        }
                        
                        echo '<small style="color: #999; font-size: 11px;">' . date('M d, Y', strtotime($review['CreatedDate'])) . '</small>';
                        echo '</div>';
                    }
                } else {
                    echo '<p style="text-align: center; color: #666;">No reviews yet. Be the first to share your experience!</p>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Review Modal -->
    <div id="review-modal" class="review-modal modal">
        <div class="review-modal-content modal-content">
            <span class="close-btn" id="close-modal-btn">&times;</span>
            <h2>Share Your Experience</h2>
            
            <div id="user-info-section"></div>
            
            <!-- Feedback Type Selection -->
            <div class="feedback-type-selection">
                <h4>What would you like to review?</h4>
                <div class="feedback-type-buttons">
                    <button class="feedback-type-btn" data-type="Order">📦 Recent Order</button>
                    <button class="feedback-type-btn" data-type="Reservation">🎉 Recent Reservation</button>
                    <button class="feedback-type-btn active" data-type="General">⭐ General Experience</button>
                </div>
            </div>
            
            <!-- Reviewable Orders Section -->
            <div class="reviewable-items-section" id="orders-section">
                <h4>Select an order to review:</h4>
                <div id="orders-list"></div>
            </div>
            
            <!-- Reviewable Reservations Section -->
            <div class="reviewable-items-section" id="reservations-section">
                <h4>Select a reservation to review:</h4>
                <div id="reservations-list"></div>
            </div>
            
            <!-- Anonymous Option -->
            <div class="anonymous-option">
                <input type="checkbox" id="anonymous-checkbox">
                <label for="anonymous-checkbox">Post anonymously</label>
            </div>
            
            <div class="overall-rating-input">
                <h3>Overall Rating</h3>
                <div class="rating-stars" id="overall-stars">
                    <span class="rating-star" data-rating="1">★</span>
                    <span class="rating-star" data-rating="2">★</span>
                    <span class="rating-star" data-rating="3">★</span>
                    <span class="rating-star" data-rating="4">★</span>
                    <span class="rating-star" data-rating="5">★</span>
                </div>
            </div>

            <div class="review-form-section">
                <h4>Rate Each Category</h4>
                
                <div class="rating-category">
                    <label>🍽️ Food Taste & Quality</label>
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
                    <label>📏 Portion Size</label>
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
                    <label>👨‍💼 Customer Service</label>
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
                    <label>✨ Ambience</label>
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
                    <label>🧹 Cleanliness</label>
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
                <h4>General Comments (Optional)</h4>
                <textarea class="comment-field" id="review-message" placeholder="Share your overall experience..." style="min-height: 80px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button id="submit-review-btn" class="submit-review-btn" style="flex: 1;">Submit Review</button>
            </div>
        </div>
    </div>

    <!-- Cart Modal (kept from original) -->
    <div id="cart-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Your Cart</h2>
            <div id="cart-items-list"></div>
            <div class="cart-summary">
                <strong>Total: ₱<span id="cart-total">0.00</span></strong>
            </div>
            <button id="checkout-btn">Proceed to Checkout</button>
        </div>
    </div>

    <footer>
        <div class="contact-section">
            <div class="container">
                <div class="contact-info">
                    <h2>Contact Us</h2>
                    <p>Have any questions? We'd love to hear from you.</p>
                    <div class="info-group">
                        <div class="info-item visit-us">
                            <img src="Photo/VisitUs.png" alt="Location" class="icon">
                            <div><strong>Visit us</strong><p>Poblacion 2, Vinzons Avenue,<br>Vinzons, Camarines Norte</p></div>
                        </div>
                        <div class="info-item call-us">
                            <img src="Photo/Selpon.png" alt="Phone" class="icon">
                            <div><strong>Call us</strong><p>09380839641</p></div>
                        </div>
                        <div class="info-item connect-us">
                            <img src="Photo/Facebook.png" alt="Facebook" class="icon">
                            <div><strong>Connect to us</strong><a href="https://www.facebook.com/profile.php?id=100063540027038" target="_blank" class="no-underline">Tabeya, VCN</a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="review_enhanced.js"></script>
</body>
</html>
<?php $conn->close(); ?>