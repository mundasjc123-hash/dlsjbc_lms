<?php
/**
 * OPAC homepage - the public catalog, no login required.
 *
 * VISUAL ONLY FOR NOW: the search bar doesn't search yet, and the
 * "recently added" books below are hardcoded sample data. Both get
 * wired to the real database once the catalog feature is built.
 */
$sampleBooks = [
    ['title' => 'Clean Code',                 'author' => 'Robert C. Martin'],
    ['title' => 'Introduction to Algorithms',  'author' => 'Cormen, Leiserson, Rivest & Stein'],
    ['title' => 'The Pragmatic Programmer',    'author' => 'David Thomas & Andrew Hunt'],
    ['title' => 'Design Patterns',             'author' => 'Gamma, Helm, Johnson & Vlissides'],
    ['title' => 'Database System Concepts',    'author' => 'Silberschatz, Korth & Sudarshan'],
];
?>
<div class="opac-hero">
    <h1>Find your next book.</h1>

    <form class="search-bar" method="get" action="/opac/search">
        <input type="text" name="q" placeholder="Search by title, author, or subject">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="browse-links">
        <a href="#">Fiction</a>
        <a href="#">Non-Fiction</a>
        <a href="#">Reference</a>
        <a href="#">Periodicals</a>
        <a href="#">Thesis &amp; Dissertations</a>
        <a href="#">AV Materials</a>
    </div>
</div>

<div class="section-heading">
    <h2>Recently added</h2>
    <a href="#">View all</a>
</div>

<div class="book-grid">
    <?php foreach ($sampleBooks as $book): ?>
        <div class="book-card">
            <div class="book-cover"><?= htmlspecialchars($book['title']) ?></div>
            <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
            <div class="book-meta"><?= htmlspecialchars($book['author']) ?></div>
        </div>
    <?php endforeach; ?>
</div>
