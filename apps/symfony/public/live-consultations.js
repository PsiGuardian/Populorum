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
            const notice = card.querySelector("[data-live-status]");
            if (notice)
                notice.textContent = `Décompte actualisé : ${consultation.tally.total} participations. État : ${consultation.tally.phase}.`;
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
