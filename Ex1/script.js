const luniRO = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("inputActivitate");
    const btn = document.getElementById("btnAdauga");
    const lista = document.getElementById("listaActivitati");

    function adaugaActivitate() {
        const text = input.value.trim();
        if (text === "") {
            return;
        }

        const data = new Date();
        const zi = data.getDate();
        const luna = luniRO[data.getMonth()];
        const an = data.getFullYear();
        const textData = zi + " " + luna + " " + an;

        const li = document.createElement("li");
        li.textContent = text + " - adăugată la: " + textData;

        const spanSterge = document.createElement("span");
        spanSterge.textContent = "Șterge";
        spanSterge.className = "sterge";

        spanSterge.addEventListener("click", function () {
            lista.removeChild(li);
        });

        li.appendChild(spanSterge);
        lista.appendChild(li);

        input.value = "";
        input.focus();
    }

    btn.addEventListener("click", adaugaActivitate);

    input.addEventListener("keyup", function (event) {
        if (event.key === "Enter") {
            adaugaActivitate();
        }
    });
});
