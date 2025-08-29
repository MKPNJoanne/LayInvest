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
