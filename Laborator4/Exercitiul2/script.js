const luniRO = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

document.addEventListener("DOMContentLoaded", () => {
    const detalii = document.getElementById("detalii");
    const btnDetalii = document.getElementById("btnDetalii");
    const dataSpan = document.getElementById("dataProdus");
    const btnLabel = btnDetalii.querySelector(".btn-label");

    detalii.classList.add("ascuns");

    const acum = new Date();
    const zi = acum.getDate();
    const luna = luniRO[acum.getMonth()];
    const an = acum.getFullYear();
    dataSpan.textContent = `${zi} ${luna} ${an}`;

    btnDetalii.addEventListener("click", () => {
        detalii.classList.toggle("ascuns");
        btnDetalii.classList.toggle("open");

        if (detalii.classList.contains("ascuns")) {
            btnLabel.textContent = "Afișează detaliile tehnice";
        } else {
            btnLabel.textContent = "Ascunde detaliile tehnice";
        }
    });
});
