// Shopzzy Navigation & Filter Logic
document.addEventListener('DOMContentLoaded', () => {
  // Mobile Nav Toggle
  const toggleBtn = document.querySelector('.mobile-nav-toggle');
  const siteNav = document.querySelector('.site-nav');
  if (toggleBtn && siteNav) {
    toggleBtn.addEventListener('click', () => {
      siteNav.classList.toggle('mobile-open');
    });
  }

  // Live Products Filter
  const filterPills = document.querySelectorAll('.filter-pill');
  const productCards = document.querySelectorAll('.card-product');
  const searchInput = document.querySelector('.products-search-input');
  const countDisplay = document.querySelector('.filter-count');

  let activeCategory = 'all';
  let searchTerm = '';

  function applyFilters() {
    let visibleCount = 0;
    productCards.forEach((card) => {
      const category = card.getAttribute('data-category') || '';
      const name = (card.querySelector('.product-name')?.textContent || '').toLowerCase();
      
      const matchesCat = activeCategory === 'all' || category.toLowerCase() === activeCategory.toLowerCase();
      const matchesSearch = searchTerm === '' || name.includes(searchTerm.toLowerCase());

      if (matchesCat && matchesSearch) {
        card.style.display = 'flex';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    if (countDisplay) {
      countDisplay.textContent = `Showing ${visibleCount} products`;
    }
  }

  filterPills.forEach((pill) => {
    pill.addEventListener('click', (e) => {
      e.preventDefault();
      filterPills.forEach((p) => p.classList.remove('filter-active'));
      pill.classList.add('filter-active');
      activeCategory = pill.getAttribute('data-filter') || 'all';
      applyFilters();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchTerm = e.target.value.trim();
      applyFilters();
    });
  }
});
