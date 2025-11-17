const luniRO = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("inputActivitate");
    const btnAdauga = document.getElementById("btnAdauga");
    const lista = document.getElementById("listaActivitati");
    const counter = document.getElementById("counterActivitati");
    const dataCurenta = document.getElementById("dataCurenta");

    const azi = new Date();
    dataCurenta.textContent = `${azi.getDate()} ${luniRO[azi.getMonth()]} ${azi.getFullYear()}`;

    function actualizeazaCounter() {
        counter.textContent = lista.children.length.toString();
    }

    function creeazaElement(textActivitate) {
        const li = document.createElement("li");

        const acum = new Date();
        const zi = acum.getDate();
        const luna = luniRO[acum.getMonth()];
        const an = acum.getFullYear();
        const dataText = `${zi} ${luna} ${an}`;

        const content = document.createElement("div");
        content.classList.add("item-content");

        const activitySpan = document.createElement("span");
        activitySpan.classList.add("activity-text");
        activitySpan.textContent = textActivitate;

        const dateSpan = document.createElement("span");
        dateSpan.classList.add("activity-date");
        dateSpan.textContent = `Adăugată la: ${dataText}`;

        content.appendChild(activitySpan);
        content.appendChild(dateSpan);

        const deleteBtn = document.createElement("button");
        deleteBtn.classList.add("delete-btn");
        deleteBtn.textContent = "Șterge";

        deleteBtn.addEventListener("click", () => {
            li.remove();
            actualizeazaCounter();
        });

        li.appendChild(content);
        li.appendChild(deleteBtn);

        return li;
    }

    function adaugaActivitate() {
        const textActivitate = input.value.trim();
        if (textActivitate === "") return;

        const li = creeazaElement(textActivitate);
        lista.appendChild(li);

        input.value = "";
        input.focus();
        actualizeazaCounter();
    }

    btnAdauga.addEventListener("click", adaugaActivitate);

    input.addEventListener("keyup", event => {
        if (event.key === "Enter") {
            adaugaActivitate();
        }
    });

    actualizeazaCounter();
});
