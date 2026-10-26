document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu
    const burger = document.getElementById('hamburger');
    const links = document.getElementById('navLinks');
    if (burger && links) burger.addEventListener('click', () => links.classList.toggle('open'));
  
    // Favourite toggle on task cards
    document.querySelectorAll('.fav').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const on = btn.classList.toggle('on');
        btn.textContent = on ? '♥' : '♡';
      });
    });
  
    // Don't submit an empty search
    const form = document.querySelector('.search-box');
    if (form) form.addEventListener('submit', e => {
      if (!form.q.value.trim()) { e.preventDefault(); form.q.focus(); }
    });
  });