/*
 * "Read more" inside a career modal: points the modal's [data-read-more] link at the
 * directory entry for whichever career opened it (the button's data-read-more-url).
 */
document.addEventListener('show.bs.modal', function (event) {
    var link = event.target.querySelector('[data-read-more]');
    if (!link) return;
    var url = event.relatedTarget && event.relatedTarget.getAttribute('data-read-more-url');
    link.hidden = !url;
    if (url) link.href = url;
});
