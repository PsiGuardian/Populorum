/* A graph link opens its matching text. All text remains accessible without JavaScript. */
function revealCausalElement() {
    const id = window.location.hash.slice(1);
    if (!id.startsWith("causal-")) return;
    const element = document.getElementById(id);
    if (!element) return;
    if (element.tagName === "DETAILS") element.open = true;
    element.scrollIntoView({ block: "start" });
}
window.addEventListener("hashchange", revealCausalElement);
window.addEventListener("DOMContentLoaded", revealCausalElement);
