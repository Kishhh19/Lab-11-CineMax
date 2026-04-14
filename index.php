<?php

header('Content-Type: text/html; charset=utf-8');

$errors = [];
$searchResults = [];
$searchQuery = trim($_GET['query'] ?? '');

$algoliaAppId = getenv('ALGOLIA_APP_ID') ?: '0XGJH71QMS';
$algoliaAdminApiKey = getenv('ALGOLIA_ADMIN_API_KEY') ?: '9fada231904887306e848f8e47f5b35b';
$algoliaIndexName = getenv('ALGOLIA_INDEX_NAME') ?: 'movies';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $errors[] = 'Composer dependencies are missing. Run <code>composer install</code> in this folder.';
} else {
    require __DIR__ . '/vendor/autoload.php';

    try {
        if ($searchQuery !== '') {
            $searchResults = runMovieSearch($searchQuery);
        } else {
            // Show featured movies on homepage
            $searchResults = runMovieSearch('', 20);
        }
    } catch (Throwable $exception) {
        $errors[] = 'Search failed: ' . $exception->getMessage();
    }
}

function runMovieSearch(string $query, int $limit = 25): array
{
    global $algoliaAppId, $algoliaAdminApiKey, $algoliaIndexName;

    if ($algoliaAppId === '' || $algoliaAdminApiKey === '') {
        throw new RuntimeException('Algolia credentials are not configured.');
    }

    $client = Algolia\AlgoliaSearch\SearchClient::create($algoliaAppId, $algoliaAdminApiKey);
    $index = $client->initIndex($algoliaIndexName);
    $searchResponse = $index->search($query, ['hitsPerPage' => $limit]);

    return $searchResponse['hits'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineMax - Movie Search Engine</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #000; margin: 0; padding: 0; color: #fff; }
        .page { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .hero { text-align: center; padding: 60px 20px; background: rgba(255, 255, 255, 0.05); border-radius: 20px; margin-bottom: 40px; border: 1px solid #333; }
        .hero h1 { font-size: 3rem; margin: 0; color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        .hero p { font-size: 1.2rem; color: #ccc; margin: 10px 0 30px; }
        .search-form { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .search-form input { flex: 1; max-width: 500px; padding: 15px 20px; border-radius: 50px; border: 1px solid #555; background: #222; color: #fff; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .search-form input::placeholder { color: #aaa; }
        .search-form button { padding: 15px 30px; border-radius: 50px; border: none; background: #fff; color: #000; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: background 0.3s; }
        .search-form button:hover { background: #ddd; }
        .status { margin: 20px 0; padding: 15px; border-radius: 10px; text-align: center; }
        .status.success { background: rgba(0, 255, 0, 0.1); color: #0f0; border: 1px solid #0f0; }
        .status.error { background: rgba(255, 0, 0, 0.1); color: #f00; border: 1px solid #f00; }
        .results { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 40px; }
        .movie-card { background: #111; border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.5); transition: transform 0.3s, box-shadow 0.3s; border: 1px solid #333; }
        .movie-card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0,0,0,0.7); }
        .movie-card img { width: 100%; height: 200px; object-fit: cover; }
        .movie-details { padding: 20px; }
        .movie-details h3 { margin: 0 0 10px; font-size: 1.3rem; color: #fff; }
        .movie-details p { margin: 10px 0; color: #ccc; line-height: 1.5; }
        .movie-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .tag { background: #333; border-radius: 20px; padding: 5px 12px; font-size: 0.9rem; color: #fff; border: 1px solid #555; }
        .tag.score { background: #444; color: #ffd700; border: 1px solid #ffd700; }
        @media (max-width: 768px) { .hero h1 { font-size: 2rem; } .results { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <h1>CineMax</h1>
        <p>Discover your next favorite movie with our powerful search engine.</p>
        <form method="get" class="search-form">
            <input type="text" name="query" placeholder="Search for movies..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit">Search</button>
        </form>
        <?php if ($searchQuery !== ''): ?>
            <div class="status success">
                Showing results for: <strong><?php echo htmlspecialchars($searchQuery); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="status error">
                <strong>Error:</strong> <?php echo htmlspecialchars(implode(', ', $errors)); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="results">
        <?php if (empty($searchResults)): ?>
            <div class="movie-card">
                <div class="movie-details">
                    <h3>No movies found</h3>
                    <p>Try adjusting your search query.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($searchResults as $hit): ?>
                <div class="movie-card">
                    <img src="<?php echo htmlspecialchars($hit['poster_url'] ?? ''); ?>" alt="<?php echo htmlspecialchars($hit['title'] ?? 'Movie poster'); ?>">
                    <div class="movie-details">
                        <h3><?php echo htmlspecialchars($hit['title'] ?? 'Untitled'); ?></h3>
                        <div class="movie-meta">
                            <span class="tag"><?php echo htmlspecialchars($hit['release_date'] ?? ''); ?></span>
                            <span class="tag score"><?php echo '★ ' . number_format((float)($hit['vote_average'] ?? 0), 1); ?></span>
                            <span class="tag"><?php echo htmlspecialchars($hit['original_language'] ?? ''); ?></span>
                        </div>
                        <div class="movie-meta">
                            <span class="tag"><?php echo htmlspecialchars($hit['genre'] ?? ''); ?></span>
                        </div>
                        <p><?php echo htmlspecialchars(substr($hit['overview'] ?? '', 0, 150) . (strlen($hit['overview'] ?? '') > 150 ? '...' : '')); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
