// Amélioration facultative : le formulaire et le décompte restent utilisables sans JavaScript.
const endpoint = document.currentScript?.dataset.endpoint;
const cards = document.querySelectorAll("[data-consultation-id]");
if (
    endpoint &&
    cards.length &&
    "fetch" in window &&
    "AbortController" in window
) {
    const byId = new Map(
        [...cards].map((card) => [card.dataset.consultationId, card]),
    );
    const selectedEndpoint = new URL(endpoint, window.location.href);
    selectedEndpoint.searchParams.set("ids", [...byId.keys()].join(","));
    let timer;
    let active;
    let stopped = false;
    let delay = 5000;
    function apply(state) {
        for (const consultation of state.consultations) {
            const card = byId.get(consultation.id);
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
    }
    function schedule() {
        clearTimeout(timer);
        if (!stopped && !document.hidden) timer = setTimeout(poll, delay);
    }
    async function poll() {
        if (stopped || document.hidden || active) return;
        const controller = new AbortController();
        active = controller;
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(selectedEndpoint, {
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            });
            if (!response.ok) throw new Error("Refresh unavailable");
            apply(await response.json());
            delay = 5000;
        } catch (error) {
            if (error.name !== "AbortError") delay = Math.min(delay * 2, 60000);
        } finally {
            clearTimeout(timeout);
            active = null;
            schedule();
        }
    }
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            clearTimeout(timer);
            active?.abort();
        } else schedule();
    });
    window.addEventListener("pagehide", () => {
        stopped = true;
        clearTimeout(timer);
        active?.abort();
    });
    window.addEventListener("pageshow", () => {
        stopped = false;
        schedule();
    });
    schedule();
}
