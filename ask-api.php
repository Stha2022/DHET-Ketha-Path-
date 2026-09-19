<?php
require_once __DIR__ . '/includes/auth.php';
kp_require_auth();
require_once __DIR__ . '/includes/profile.php';
header('Content-Type: application/json');
$q = strtolower(trim($_POST['question'] ?? ''));
$name = profile_get()['name'] ?: 'there';

$response = "I can help you explore your journey. Try asking about a career, qualification, subjects, your next step, or a 'What If?' scenario.";
$tag = "Journey guidance";

if (str_contains($q, 'computer') || str_contains($q, 'coding') || str_contains($q, 'software')) {
    $response = "If you enjoy computers and building things, Software Development is one pathway worth exploring. Khetha would next help you check relevant subjects, qualifications and learning providers before you decide.";
    $tag = "Career exploration";
} elseif (str_contains($q, 'qualify') || str_contains($q, 'don’t qualify') || str_contains($q, "don't qualify")) {
    $response = "Not qualifying for your first route does not have to end the journey. Open What If? to compare alternative routes toward a related goal. The production version would calculate these alternatives from approved NCAP/DHET pathway data.";
    $tag = "Pathway adaptation";
} elseif (str_contains($q, 'why') || str_contains($q, 'suggest')) {
    $response = "The prototype is using your starting context — your level, subjects and interests — to make the journey more personal. In production, Khetha should explain which approved data and rules contributed to every recommendation.";
    $tag = "Explainability";
} elseif (str_contains($q, 'next') || str_contains($q, 'what should')) {
    $response = "Your next step is to explore the qualification routes connected to your chosen career, then compare learning providers. Khetha keeps those steps together instead of making you restart your search each time.";
    $tag = "Next action";
}

echo json_encode(['answer'=>$response, 'tag'=>$tag, 'name'=>$name]);
?>