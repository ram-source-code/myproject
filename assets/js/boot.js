/* Runs before first paint (tiny, render-blocking by design): lets CSS know JavaScript is available,
   so JS-enhanced layouts (e.g. the mobile filter sheet) render correctly on the first frame. */
document.documentElement.classList.add('js');
