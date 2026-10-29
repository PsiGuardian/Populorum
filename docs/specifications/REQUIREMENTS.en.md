# GENERAL REQUIREMENTS SPECIFICATION — DÉMOS KRATOS

### Δῆμος Κράτος — Power belongs to the people

**Version:** 1.0 — First complete draft  
**Date:** 29 October 2026  
**Language:** English — translation of the French reference specification  
**Status:** Public design proposal; subject to review and approval before production  
**Initial territory:** France  
**Project:** Fictional, experimental civic-democracy simulator, intended to become free and open source  
**Reference technology:** Symfony 8.1, PHP 8.4, PostgreSQL

> “Let no one be condemned for thinking differently. Let each person simply be invited to explain their path, so that everyone may learn from the consequences of their own decisions.”

**Emergent convergence:** “Do not impose your path. Illuminate your own, and let the consequences teach everyone.”

**Important notice:** Démos Kratos is a **fictional simulation project**, not an official election, a government service or a mechanism producing legally binding public decisions. Demonstration identities and consultations are simulated. Publishing this specification does not announce the opening of real civic voting.

The [French specification](REQUIREMENTS.fr.md) is authoritative for interpretation; this English edition is a working translation preserving its numbered structure and requirements.

---

# PART I — PROJECT FOUNDATIONS

## 1. General vision

Démos Kratos aims to design and study an experimental form of civic participation based on individual responsibility, collective memory and emergent convergence.

A citizen should be able to identify a problem, describe direct, indirect, probable and observed causes, propose their independent path, explain anticipated consequences, freely explore others' paths, join an experimental consultation, observe consequences and revise their reasoning.

The objective is not to determine which person possesses the best opinion. It is to preserve a structured memory of decisions, experiences and consequences for future generations.

## 2. Fundamental objective

Enable collective decision-making without imposing uniformity of thought through **individual freedom**, **collective experience**, **emergent convergence** and **verifiable transparency**. Convergence is possible, never guaranteed or compelled.

## 3. Non-negotiable principles

- **P-01 — Civic equality:** one vote per eligible person per consultation.
- **P-02 — Freedom of thought:** no imposed intellectual path.
- **P-03 — Non-judgement:** no ranking of people's worth by political opinion.
- **P-04 — Path autonomy:** explain one's own reasoning rather than personally contradict others.
- **P-05 — Fact observation:** consequences may be measured, documented and verified.
- **P-06 — Right to make mistakes:** an incorrect prediction is not a moral failing.
- **P-07 — Right to revise:** everyone may change their reasoning.
- **P-08 — Collective memory:** proportionate record retention within legal limits.
- **P-09 — Municipal autonomy:** territorial decisions and experiments.
- **P-10 — Transparency:** auditable calculations, expenditure and operations.
- **P-11 — No technical privilege:** administration never confers extra votes.
- **P-12 — Voluntary participation:** no one must join or disclose their beliefs.
- **P-13 — Protection of people:** prevent pressure, harassment and identity theft.
- **P-14 — Independence:** no one actor can unilaterally capture the project.
- **P-15 — Reversibility:** ability to correct, migrate and reconsider governance.

## 4. Legal and institutional scope

Distinguish an **experimental proposal without legal effect**, an **experiment conducted with authorised local authorities**, and an **official act adopted by a legally competent institution**. The pedagogical phrase “experimental law” must not suggest real legal force.

The “collective presidency” symbolises the people's collective decision in the simulation; it does not replace the French president or institutions. Real actions follow statutory powers and procedures. Publication of named political views requires suitable legal grounds and safeguards before any real-world deployment.

# PART II — TERRITORIAL ORGANISATION

## 5. French geographic reference

Import the INSEE Official Geographic Code, 2026 edition, preserving identifiers, editions and change history for France, regions, departments, municipalities and special collectivities. Model inter-municipal structures separately. Distinguish municipalities from town halls, municipal districts, delegated/associated communes and overseas statuses. Trace mergers, creations, renamings and affiliations.

## 6. Territorial record

Each municipality record contains official name, INSEE code, region/department, status, sourced population with reference year, historic affiliations, proposals, consultations, experiments, observations, adaptations, exclusions and assessments. Town-hall contacts, where provided, must carry an identified source and update date.

## 7. Municipal autonomy

Each municipality has independent experimental decisions and history. A local vote **never applies automatically** elsewhere. Support local proposals, voluntary reuse, separate votes across municipalities, territorial adaptations and non-applicability explanations. Differences concern circumstances, not people's worth.

## 8. Territorial non-applicability record

An adaptation or exclusion records the proposal, territory, relevant local circumstances, causes, expected and observed effects, sources, alternatives and reconsideration conditions. Administrators cannot impose an unlogged exclusion at their discretion.

## 9. Territorial expansion

The first demonstration covers France. Data structures and interfaces must support future countries with their own geography, eligibility rules and legal environments, without rebuilding the whole system.

# PART III — CIVIC IDENTITY AND PARTICIPATION

## 10. User categories

| Role | Responsibilities |
| --- | --- |
| Visitor | Read public information |
| Participating citizen | Propose paths, observe, vote |
| Citizen verifier | Examine proofs and report anomalies |
| Technical contributor | Code and documentation |
| Territorial observer | Contribute verifiable observations |
| Conciliator | Address conduct incidents, not opinions |
| Auditor | Verify methods and results |
| Node operator | Host or relay authorised information |
| Technical administrator | Availability and security |
| Collegial governance | Sensitive decisions and appeals |

Technical roles never award additional political voting power.

## 11. Civic identity

Voluntary named participation combines uniqueness, territorial eligibility, verified public identity, prior disclosure and consent where required. Browsing public information remains anonymous. **The initial demonstration uses exclusively fictional identities and votes**, clearly labelled. Civil identity, territorial eligibility and displayed identity are distinct. No real-world named processing of political opinion until legal and technical assessment.

## 12. Citizen account

Potential fields: stable ID, public name, municipality, verification status, relevant territory history, language, accessibility, security, consent records, contributions, voluntary public consultation history and versioned paths. Credentials, ID evidence, private contacts and home addresses never appear in the public register.

## 13. Participant rights

Provide access, correction, export, permitted revision, verified changes of municipality, withdrawal of consent where applicable, lawful erasure, appeal against suspension and departure from the platform. Public memory must respect statutory rights; do not promise simultaneously irreversible named publication and unrestricted erasure. Separate durable cryptographic proofs from personal data as far as possible.

## 14. Multiple-identity prevention

Risk-proportionate verification, eligibility register, transactional uniqueness, detection of attempts and appeal process for false positives. IP addresses and device identifiers alone do not establish identity. A municipality change cannot confer invalid double eligibility.

# PART IV — INDIVIDUAL PATHS

## 15. Definition of a path

A **path** is an autonomous unit of citizen reasoning, not a reply attacking another person. It includes author, territory, initial problem, creation date, version, causes, hypotheses, observations, solutions, forecast and actual consequences, alternatives and revisions.

## 16. Cause-and-consequence tree

Twelve mandatory conceptual categories based on the charter:

| Element | Meaning |
| --- | --- |
| Problem | Situation to understand |
| Direct cause | Immediately responsible mechanism |
| Indirect cause | Contributing factor |
| Probable cause | Explanatory hypothesis |
| Observed cause | Documented fact |
| Proposed solution | Possible action |
| Probable beneficial consequence | Expected benefit |
| Probable harmful consequence | Expected risk |
| Observable consequence | Verifiable indicator |
| Unforeseen consequence | Unanticipated effect |
| Alternative solution | Another possible path |
| Revision | Development of reasoning |

The taxonomy is extensible without deleting pre-existing records.

## 17. Additional categories

Reversibility, irreversibility, time to effect, duration, uncertainty, external factor, indirect effect, material cost, human effort, impacted populations, stop condition, success criterion and failure criterion. These describe experiments without morally grading their authors.

## 18. Cause-and-effect relationships

Typed links: “causes”, “contributes to”, “depends on”, “enables”, “may produce”, “observed after”, “supported by”, “superseded by” and “alternative to”. Distinguish correlation, chronology, hypothesis and demonstrated causality. Represent uncertainty and confounding factors.

## 19. Version history

Every meaningful update creates an identifiable revision comparing hypotheses, observations, solutions and reasoning over time. Previous versions are not automatically labelled “error” or later versions “truth”. Preserve history only within applicable data-protection rights.

## 20. Coexistence without personal contradiction

Primary UI actions: **Explain my path**, **Add a cause**, **Add an observation**, **Add a source**, **Suggest another solution**, **Revise my path**, **Review consequences**, **Create an alternative path**. Avoid “Contradict this person”, intellectual superiority ratings and moral scores. Sourced factual corrections can address evidence, never people's human value.

# PART V — PROPOSALS AND CONSULTATIONS

## 21. Creating a proposal

Workflow: choose territory, define problem, write plain-language description, cause/consequence/solution/justification summary, construct causal tree, cite evidence, describe solution, define indicators, address risks and reversibility, publish. Drafts may be incomplete; consultations require the completed mandatory fields.

## 22. Comprehensible language

Each proposal includes a short explanation, a complete explanation, the **CAUSE → CONSEQUENCE → SOLUTION → JUSTIFICATION** summary, glossary and optional adapted wording. Plain French is the demonstration baseline; colloquial, local, slang and translated versions may aid understanding without changing the meaning. Translations and reformulations are versioned and verifiable.

## 23. Proposal lifecycle

**Draft → Published → Completed → Ready for consultation → Consultation open → Consultation closed → Result announced → Experiment → Observation → Assessment → Archive or revision.**

Additional states: author withdrawn, suspended for technical/legal verification, duplicated with attribution, associated with an independent path, outside municipal powers, territorially inapplicable, postponed or superseded. Every transition is dated, justified and audited.

## 24. Freezing the proposal under consultation

The exact proposal, material attachments and version are frozen upon opening. Substantial changes require a new version and, according to the published rules, a fresh consultation. Silent edits after votes are cast are prohibited.

## 25. Consultation parameters

Identifier, municipality, exact proposal/version, schedule, eligibility rules, identity checks, named-publication procedure, quorum, majority, abstentions, ties, cancellation, appeals and publication schedule. Result-affecting parameters must be public **before opening** and immutable thereafter.

## 26. Citizen vote

Three choices: **FOR**, **AGAINST**, **ABSTAIN**. The choice does not evaluate people. Participants may explain the reasoning behind their vote. An absent explanation does not arbitrarily invalidate a vote. Eligibility and validity criteria are defined before the consultation.

## 27. Changing a vote

Where the rules allow, votes may be changed while the consultation is open, with an auditable history and **only one active vote** per person. After closing, the historic result remains frozen; later changes of opinion are recorded in personal paths or new consultations.

## 28. Live results

Display FOR, AGAINST, ABSTAIN, participating citizens, eligible population where reliably determined, turnout, timestamps, provisional/final status and pending technical validation. Named lists are shown only for legally authorised voluntary public participation. Warn that live results may influence voter behaviour and expose citizens to pressure.

## 29. Tallying

Apply previously published rules for expressed votes, abstention, majority, quorum, ties and participation. A zero-participant result cannot cause division by zero or fictitious majority. Enable independent recalculation. Correction or cancellation follows an auditable published procedure.

# PART VI — EXPERIMENTS AND CONSEQUENCES

## 30. Definition of an experiment

Study of a measure and its outcome: wholly simulated, historical open-data analysis, new observation or an authorised project with a real municipality. Label simulations conspicuously. Never present fabricated consequences as observed real-world facts.

## 31. Experimental protocol

Include objective, hypothesis, baseline, observation period, predefined indicators, sources, external factors, potential benefits/harms, stop criteria, evaluation method and analytical limits. Prefer registering indicators beforehand to avoid post-hoc selection favouring a result.

## 32. Observed consequences

Record date, indicator, baseline and measured value, units, method, source, uncertainty, territory, affected population if necessary, confounders and hypothetical causal links. The quality of evidence is not a moral judgement on its author.

## 33. Predicted-versus-actual assessment

Classify effects as consistent with expectations, partly consistent, contrary direction, not yet measurable, insufficiently documented or unforeseen. Never automatically categorise citizens as “good” or “bad” decision-makers.

## 34. Reversibility

Prefer an experiment with a defined rollback. Flag irreversible effects before consultation. A majority alone cannot authorise measures seriously endangering rights, health, safety or the environment.

## 35. Municipal assessment

Document measures, hypotheses, outcomes, unforeseen effects, costs, local adaptations, circumstances and decisions to continue, change or stop. Link reported information to its source and period.

# PART VII — EMERGENT CONVERGENCE

## 36. Technical definition

The voluntary, gradual rapprochement of solutions or findings developed through independent paths. Analyse recurring independent proposals, shared causal concepts and indicators, territorial outcomes and temporal changes, **without ranking citizens**.

## 37. Convergence map

Visualise distinct paths, parallel paths, partial convergence, empirically observed convergence and persistent divergence, along with relevant local differences. Convergence is never an individual obligation.

## 38. Analytical methods

Causal graphs, thematic grouping, solution similarity, time-series analysis, descriptive statistics, territory comparisons and missing-data detection. Criteria must be documented, explainable and recommendations disableable. No political, intellectual or moral scoring of citizens.

## 39. Limits of convergence

Statistical similarity, conceptual convergence and empirical proof are different things. Similar outcomes do not establish common causation. A majority of converging paths is not itself scientific proof.

# PART VIII — PUBLIC TRANSPARENCY

## 40. Public registry

Publish, within legal limits: proposals, versions, consultation rules, results, integrity proofs, assessments, costs, technical changes, confirmed anomalies, corrections, suspensions, governance decisions and publishable appeals. Protected personal data remain separate.

## 41. Event log

Events include identifier, type, UTC timestamp, authorised origin, object, version, predecessor reference, digest, applicable signature and validation status. Detect broken chains and unauthorised modification. A cryptographic hash alone does not prove the truth of a fact or a person's civil identity.

## 42. Public evidence

Use content digests, digital signatures, chaining, Merkle trees or equivalents, public checkpoints, independent copies, uniqueness checks, independent tally calculation and cross-node comparisons. Do not require protected personal information to be included in publicly auditable proofs.

## 43. Independent verification

Publish an open verification tool able to retrieve permissible data, verify signatures and event continuity, recompute results, compare local and official published records, produce discrepancy reports and submit supporting evidence. Document algorithms and reproducible test fixtures.

## 44. Discrepancy alerts

Categories: **unverified report**, **reproducible divergence**, **security incident**, **demonstrated fraud**. A single report may trigger investigation, not a presumption of guilt. Confirmed discrepancies affecting results require proportionate public notification.

# PART IX — DISTRIBUTED INFRASTRUCTURE

## 45. Vision

Evolve towards voluntarily hosted nodes, reducing dependence on one operator and allowing independent verification. Distribution is experimental and does not automatically guarantee integrity.

## 46. Node types

**Read-only consultation node** (public pages), **relay** (authorised events), **mirror** (verifiable data), **auditor** (independent recalculation), **application node** (authorised services), **eligibility authority** (participant entitlement subject to explicit governance). A volunteer node never gains voting authority merely by joining the network.

## 47. Voluntary bandwidth sharing

Optional agent with proposed default ceilings: **at most 50% of authorised upstream capacity while apparently idle**, **10% while in use**. These are user-configurable upper limits, not mandated consumption. Personal network traffic always takes priority. Capacity estimates are inherently approximate and must err on the conservative side.

## 48. Volunteer agent controls

Enable/disable, immediate pause, maximum rate, monthly data allowance, permitted hours, metered-connection avoidance, battery and thermal cut-offs, usage indicator, technical history, software version, update policy and immediate permission withdrawal. Do not spy on personal browsing to determine traffic load.

## 49. Replicated data

Permissible public data: proposals, publishable results, cryptographic evidence, log digests, open territorial references, lawful public observations and static resources. Never automatically replicate passwords, civil ID proofs, home addresses, private keys, confidential logs or sensitive named political data without a lawful and technically enforceable basis.

## 50. Resilience

Test node dropouts, server loss, corrupted copies, desynchronisation, invalid events, replay attacks, temporary congestion, central read-service outages and protocol version differences. Define a consensus and authority model before any sensitive live deployment.

## 51. Specific threats

Sybil identities, colluding nodes, eclipse attacks, denial of service, invalid signatures, compromised authority keys, network partitions, false timestamps, pre-signature data manipulation, censorship, personal-data leaks and inconsistent versions. Protect volunteer node operators as well as platform participants.

## 52. Deployment sequence

**A:** single server, verifiable journal.  
**B:** independent public mirrors.  
**C:** volunteer replication and verification nodes.  
**D:** federated services with explicit consensus/governance.  
**E:** large-scale simulation.

Each progression depends on testing, audit and documented acceptance.

