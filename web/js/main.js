// main.js
window.addEventListener("scroll", function () {
    const header = document.querySelector("header");
    if (window.scrollY > 20) { 
        header.classList.add("scrolled");
    } else {
        header.classList.remove("scrolled");
    }
});

//alert message 
document.addEventListener("DOMContentLoaded", function () {
    setTimeout(function () {
        const successAlert = document.querySelector(".alert-success");
        if (successAlert) {
            successAlert.style.transition = "opacity 0.5s ease, transform 0.5s ease";
            successAlert.style.opacity = "0";
            successAlert.style.transform = "translateY(-6px)";
            setTimeout(() => successAlert.remove(), 500);
        }
    }, 4000);
});

//  GridView pagination
// For each section id, find its pager links 
document.addEventListener("DOMContentLoaded", function () {
    const sections = ["production", "revenue", "feed", "costs", "cull", "prices"];

    sections.forEach((sectionId) => {
        const section = document.getElementById(sectionId);
        if (!section) return;

        // find the pager inside this section (GridView renders ul.pagination)
        const pager = section.querySelector(".pagination");
        if (!pager) return;

        pager.querySelectorAll("a").forEach((a) => {
            try {
                // Build absolute URL and add fragment
                const url = new URL(a.getAttribute("href"), window.location.origin);
                url.hash = sectionId;
                a.setAttribute("href", url.toString());
            } catch (e) {
                // In case of relative routes without proper base (unlikely), fallback:
                const href = a.getAttribute("href") || "";
                if (!href.includes("#" + sectionId)) {
                    a.setAttribute("href", href + (href.includes("?") ? "" : "") + "#" + sectionId);
                }
            }
        });
    });
});