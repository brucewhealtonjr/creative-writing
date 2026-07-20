// * Bootstrap libraries
import './_bootstrap';

// * Any other global site-wide JavaScript should be placed below.

document.addEventListener('DOMContentLoaded', () => {
  // Smooth scroll for hero buttons
  const exploreBooksBtn = document.querySelector('a[href="#books-section"]');
  const exploreWritingsBtn = document.querySelector('a[href="#writings-section"]');

  if (exploreBooksBtn) {
    exploreBooksBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector('.view-books-by-bruce');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (exploreWritingsBtn) {
    exploreWritingsBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector('.view-recent-writing');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  const viewsToCarousellize = [
    '.view-books-by-bruce',
    '.view-recent-writing'
  ];

  viewsToCarousellize.forEach(selector => {
    const views = document.querySelectorAll(selector);
    
    views.forEach(view => {
      const content = view.querySelector('.view-content');
      if (!content || content.children.length === 0) return;

      // 1. Wrap the content container in a relative wrapper
      const wrapper = document.createElement('div');
      wrapper.className = 'carousel-view-wrapper';
      content.parentNode.insertBefore(wrapper, content);
      wrapper.appendChild(content);

      // 2. Create Prev / Next buttons
      const prevBtn = document.createElement('button');
      prevBtn.className = 'carousel-btn prev-btn';
      prevBtn.setAttribute('aria-label', 'Previous');
      prevBtn.innerHTML = '&#8249;'; 
      
      const nextBtn = document.createElement('button');
      nextBtn.className = 'carousel-btn next-btn';
      nextBtn.setAttribute('aria-label', 'Next');
      nextBtn.innerHTML = '&#8250;';

      // Append buttons to the wrapper
      wrapper.appendChild(prevBtn);
      wrapper.appendChild(nextBtn);

      // 3. Click listeners
      prevBtn.addEventListener('click', () => {
        const cardWidth = content.firstElementChild ? content.firstElementChild.offsetWidth : 300;
        const gap = 28;
        content.scrollBy({ left: -(cardWidth + gap), behavior: 'smooth' });
      });

      nextBtn.addEventListener('click', () => {
        const cardWidth = content.firstElementChild ? content.firstElementChild.offsetWidth : 300;
        const gap = 28;
        content.scrollBy({ left: cardWidth + gap, behavior: 'smooth' });
      });
      
      // 4. Show/hide buttons based on scroll position
      const toggleButtons = () => {
        const maxScroll = content.scrollWidth - content.clientWidth;
        prevBtn.style.display = content.scrollLeft <= 5 ? 'none' : 'flex';
        nextBtn.style.display = content.scrollLeft >= maxScroll - 5 ? 'none' : 'flex';
      };
      
      content.addEventListener('scroll', toggleButtons);
      window.addEventListener('resize', toggleButtons);
      
      // Initial call to set correct button states
      setTimeout(toggleButtons, 400);
    });
  });
});
