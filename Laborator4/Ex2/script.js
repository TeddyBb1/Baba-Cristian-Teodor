const luniRO = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

document.addEventListener("DOMContentLoaded", function () {
    const detalii = document.getElementById("detalii");
    const btn = document.getElementById("btnDetalii");
    const dataSpan = document.getElementById("dataProdus");

    detalii.style.display = "none";

    const acum = new Date();
    const textData = acum.getDate() + " " + luniRO[acum.getMonth()] + " " + acum.getFullYear();
    dataSpan.textContent = textData;

    btn.addEventListener("click", function () {
        if (detalii.style.display === "none") {
            detalii.style.display = "block";
            btn.textContent = "Ascunde detalii";
        } else {
            detalii.style.display = "none";
            btn.textContent = "Afișează detalii";
        }
    });
});
