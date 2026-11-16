<?php
// client/review.php
require_once __DIR__ . '/../backend/db.php';

$message = '';
$booking = null;
$bookingId = intval($_GET['booking_id'] ?? $_GET['id'] ?? 0);

if ($bookingId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    $clientName = trim($_POST['client_name'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $sessionRating = intval($_POST['session_rating'] ?? 5);
    $overallRating = intval($_POST['overall_rating'] ?? 5);
    $comments = trim($_POST['review_comments'] ?? '');

    if (!empty($clientName) && !empty($clientEmail)) {
        $stmt = $pdo->prepare("INSERT INTO reviews (client_name, client_email, service, session_rating, overall_rating, review_comments) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$clientName, $clientEmail, $service, $sessionRating, $overallRating, $comments]);
        $message = "Thank you so much! Your feedback has been recorded successfully.";
    } else {
        $message = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Experience - barewithtea</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; color: #212529; }
        .review-card { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 35px; border: 1px solid #e9ecef; }
        .star-rating { font-size: 2rem; color: #ffc107; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <div class="review-card">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-uppercase tracking-wide"><i class="fa-solid fa-gem text-warning me-2"></i>barewithtea.</h2>
            <p class="text-muted">Rate Your Treatment & Experience</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success text-center border-0 shadow-sm">
                <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($message) ?>
            </div>
            <div class="text-center mt-4">
                <a href="../index.html" class="btn btn-dark btn-lg">Return to Home</a>
            </div>
        <?php else: ?>
            <form method="POST" action="review.php">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id'] ?? 0) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Your Full Name</label>
                    <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($booking['client_name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Your Email Address</label>
                    <input type="email" name="client_email" class="form-control" value="<?= htmlspecialchars($booking['client_email'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Service Received</label>
                    <input type="text" name="service" class="form-control" value="<?= htmlspecialchars($booking['service'] ?? 'Boutique Treatment') ?>" required>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Session Quality (1 to 5)</label>
                        <select name="session_rating" class="form-select">
                            <option value="5">5 - Excellent / Perfect</option>
                            <option value="4">4 - Very Good</option>
                            <option value="3">3 - Satisfactory</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Overall Rating (1 to 5)</label>
                        <select name="overall_rating" class="form-select">
                            <option value="5">5 Stars ★★★★★</option>
                            <option value="4">4 Stars ★★★★☆</option>
                            <option value="3">3 Stars ★★★☆☆</option>
                            <option value="2">2 Stars ★★☆☆☆</option>
                            <option value="1">1 Star ★☆☆☆☆</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Your Feedback & Comments</label>
                    <textarea name="review_comments" class="form-control" rows="4" placeholder="Tell us how your treatment went or any suggestions..."></textarea>
                </div>

                <button type="submit" class="btn btn-dark w-100 btn-lg fw-bold"><i class="fa-solid fa-paper-plane me-2"></i> Submit Feedback</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
