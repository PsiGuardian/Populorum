// Amélioration facultative : le formulaire et le décompte restent utilisables sans JavaScript.
const cards = document.querySelectorAll("[data-consultation-id]");
if (cards.length && "EventSource" in window) {
    const source = new EventSource("/api/v1/events");
    source.addEventListener("state", (event) => {
        let state;
        try {
            state = JSON.parse(event.data);
        } catch {
            return;
        }
        for (const consultation of state.consultations) {
            const card = [...cards].find(
                (item) => item.dataset.consultationId === consultation.id,
            );
            if (!card) continue;
            for (const choice of ["pour", "contre", "abstention"]) {
                const counter = card.querySelector(`[data-count="${choice}"]`);
                const bar = card.querySelector(`.vote-bar .${choice}`);
                if (counter) counter.textContent = consultation.tally[choice];
                if (bar)
                    bar.style.width = `${consultation.tally.total ? (100 * consultation.tally[choice]) / consultation.tally.total : 0}%`;
            }
            const unit =
                consultation.tally.unit === "family"
                    ? "familles"
                    : "personnages";
            const summary = card.querySelector("[data-electoral-summary]");
            if (summary)
                summary.textContent = `${consultation.tally.total} / ${consultation.tally.eligible} ${unit} · ${consultation.tally.participation.toFixed(1)} % de participation · quorum ${summary.dataset.quorum} %`;
            const individual = card.querySelector("[data-individual-summary]");
            if (individual)
                individual.textContent = `Opinions individuelles : ${consultation.tally.individual.pour} POUR, ${consultation.tally.individual.contre} CONTRE, ${consultation.tally.individual.abstention} ABSTENTIONS. Elles restent distinctes du suffrage familial.`;
            const accessibleBar = card.querySelector(".vote-bar");
            if (accessibleBar)
                accessibleBar.setAttribute(
                    "aria-label",
                    `${consultation.tally.pour} pour, ${consultation.tally.contre} contre, ${consultation.tally.abstention} abstentions ; unité : ${unit}`,
                );
            const notice = card.querySelector("[data-live-status]");
            if (notice)
                notice.textContent = `Décompte actualisé : ${consultation.tally.total} ${unit} participantes. État : ${consultation.tally.phase}.`;
            if (consultation.tally.phase !== "open") {
                card.querySelectorAll(".consultation-actions a").forEach(
                    (link) => {
                        link.removeAttribute("href");
                        link.setAttribute("aria-disabled", "true");
                    },
                );
            }
        }
    });
    window.addEventListener("pagehide", () => source.close(), { once: true });
}
