> Experimental amendment, 29 October 2026: citizens retain individual reasoning and FOR/AGAINST/ABSTAIN positions. Each declared eligible family contributes at most one adoption vote, regardless of size. Strict majority of expressed FOR/AGAINST within the family; ties or abstentions only mean family abstention, no participant means non-participation. Quorum and adoption majority use families. Membership and aggregation are frozen at opening; no person belongs to two families in the same consultation. Historical consultations retain their frozen rules. This amendment supersedes any earlier wording that equates individual positions with adoption votes.

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

- **P-01 — Individual expression, family suffrage:** every eligible citizen may reason and express a position; each declared eligible family contributes at most one adoption vote per consultation, regardless of size.
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

Where the rules allow, votes may be changed while the consultation is open, with an auditable history and **only one active individual position** per person and at most one adoption vote per family. After closing, the historic result remains frozen; later changes of opinion are recorded in personal paths or new consultations.

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


# PART X — SYMFONY 8 ARCHITECTURE

## 53. Technology stack

| Area | Proposed technology |
| --- | --- |
| Framework | Symfony 8.1 |
| Runtime | PHP 8.4 minimum |
| Database | PostgreSQL; optional PostGIS |
| ORM/migrations | Doctrine ORM / Doctrine Migrations |
| Rendering | Twig, HTML5, CSS3, JavaScript |
| Security | Symfony Security / Validator |
| Forms | Symfony Form |
| Translations | Symfony Translation |
| API/serialization | Symfony controllers / Serializer |
| Background jobs | Symfony Messenger |
| Real time | SSE or Mercure |
| Cache | Symfony Cache; optional Redis |
| Logging | Monolog, separate business event log |
| Testing | PHPUnit and Symfony functional tests |
| Static analysis/style | PHPStan, PHP-CS-Fixer, dependency audit |
| Mapping | OpenStreetMap and suitable free tools |
| Deployment | Linux, PHP-FPM, web server, PostgreSQL |

Lock dependencies with explicit versions; regularly review their security status. Document reproducible setup on Arch Linux, preferring packages available from the system package manager where appropriate.

## 54. Modular architecture

Core modules: **Identity**, **Territory**, **Proposal**, **Pathway**, **Consultation**, **Voting**, **Tally**, **Experiment**, **Convergence**, **Transparency**, **Federation**, **Governance**, **Moderation**, **Finance**, plus cross-cutting **Localization** and optional **Assistance**.

Identity controls identity and eligibility; Territory owns geography; Proposal manages revisions; Pathway causal graphs; Consultation schedules and rule sets; Voting ballots; Tally results; Experiment observations; Convergence similarities; Transparency audits; Federation nodes; Governance decisions; Moderation harmful behaviour; Finance resources. Localization and Assistance never take authority over the core voting engine.

Business rules must be independently testable and must not reside solely in HTTP controllers.

## 55. Programming principles

Strict PHP types, object-oriented design, dependency injection, interfaces for critical services, lightweight controllers, independently testable business logic, server-side validation and authorisation, transactions, integrity constraints, audited migrations, event idempotency and presentation/domain separation. JavaScript alone never determines an official stored result; no secret privilege can fabricate votes.

## 56. Database

PostgreSQL stores relational and transactional data. Causal graphs use normalised node/edge relations with documented optional attributes. PostGIS may be used for richer geometry. Index from measured query profiles; use tracked migrations, backups and rollback procedures around destructive change.

## 57. Principal entities

**Territory:** Country, Region, Department, Municipality, MunicipalOffice, TerritoryHistory, TerritoryRelation, TerritorialCompetence.

**Identity:** Citizen, CivicIdentity, EligibilityRecord, TerritorialAffiliation, PublicationConsent, AuthenticationCredential, CitizenPreference.

**Proposals/paths:** Proposal, ProposalRevision, Pathway, PathwayRevision, CausalNode, CausalEdge, Hypothesis, Evidence, Alternative, Forecast, RevisionReason.

**Consultations:** Consultation, ConsultationRuleSet, EligibleParticipant, Vote, VoteRevision, Tally, TallySnapshot, CollectiveDecision.

**Experiments:** Experiment, ExperimentProtocol, Observation, Indicator, ObservationSource, Evaluation, TerritorialAdaptation, TerritorialExclusion.

**Integrity/network:** AuditEvent, IntegrityCheckpoint, DigitalSignature, NetworkNode, NodeAttestation, SyncCheckpoint, IntegrityAlert, IncidentReport.

**Governance:** GovernanceProposal, GovernanceDecision, ModerationCase, Appeal, FinancialEntry, ResourceContribution, TranslationRevision.

## 58. Integrity constraints

Unique territorial identifiers tied to official editions; one active individual position per citizen/consultation; one frozen family membership per person and at most one adoption vote per family; immutable proposal-version references and consultation rules; eligibility and timing checks; auditable workflows; revision history; cycle restrictions where a specific acyclic subgraph requires them; consistent financial sums; independently recomputable cryptographic digests.

## 59. API

Versioned endpoints for territories, proposals, paths, causal elements, consultations, results, observations, assessments, proofs, finances and public node status. Authenticated sensitive operations separate from public read endpoints. Document pagination, filtering, rate limits, versioning, errors and compatibility.

## 60. Domain events

ProposalCreated, ProposalRevisionPublished, ConsultationOpened, VoteRecorded, VoteChanged, ConsultationClosed, TallyPublished, ObservationRecorded, ExperimentEvaluated, IntegrityMismatchDetected, IntegrityIncidentConfirmed, NodeDisconnected, PublicCorrectionPublished. Consumers must be idempotent; redelivery cannot duplicate an operation.

# PART XI — USER INTERFACE AND EXPERIENCE

## 61. Visual direction

Contemporary civic identity suggesting dignity, freedom, knowledge, time, experience, community, convergence and transparency. Subtle classical/Stoic references without partisan symbolism. Starting palette: antique gold #C6A667, navy #111D2B, light stone #F2EEE5, white #FFFFFF, balanced green #2F6B59, alert red #B84A4A. Never communicate a state exclusively through colour.

## 62. Home page

Brand, Greek rendering, founding motto, emergent convergence, plain introduction, French map, simulated municipal consultations, path examples, recent documented experiences, expenses, charter and verifier. Display the **fictional experimental status** clearly throughout.

## 63. Main navigation

Home; My territory; Explore municipalities; Proposals; Explain my path; Consultations; Experiments; Emergent convergence; Collective memory; Transparency; Civic infrastructure; Contribute; Founding Charter. Personal dashboard for signed-in citizens.

## 64. Territorial map

Region → department → municipality navigation with local consultations, proposals, experiments, adaptations and territory comparisons. Provide a functionally equivalent accessible text list.

## 65. Path view

Public author where legally allowed, territory, linked proposal, causal tree, revisions, evidence, observations, consequences, associated independent paths and revision actions. Responsive design with a non-graph textual alternative.

## 66. Consultation dashboard

Exact proposal version, plain summary, causal graph, independent alternatives, expected consequences, live votes, provisional/final status, rules, integrity proofs, event history and observations after closure. Unverified assertions must look different from substantiated observations.

## 67. Personal memory

Chronological trail of problem discovery, proposed solution, vote, unexpected consequence, personal revision and new experience. No automatic personal “failure” labels.

## 68. Accessibility

Design for keyboard navigation, screen readers, low vision, reading difficulties, limited mobility, slow networks and older mobile devices. Target **WCAG 2.2 AA** and applicable French RGAA requirements. Essential actions cannot depend exclusively on animation, colours or visual graphs.

# PART XII — LANGUAGES AND ARTIFICIAL INTELLIGENCE

## 69. Languages

Full initial UI in French, with Symfony Translation prepared for the **28 existing documentation locales**: ar_SA, cs_CZ, de_DE, el_GR, en_CA, en_GB, en_IE, en_US, es_ES, fr_FR, hi_IN, hr_HR, hu_HU, id_ID, it_IT, ja_JP, ka_GE, mn_MN, ms_MY, nl_NL, pl_PL, pt_PT, ro_RO, ru_RU, sk_SK, tr_TR, vi_VN, zh_CN. Version translations and enable human review. Warn about any semantic difference from the voting reference text.

## 70. Linguistic reformulations

Variants: plain French, explained administrative language, simplified science, very simple wording, colloquial and documented regional expressions, useful slang and foreign languages. Adapt wording without changing the proposal's substance; identify and review every variant.

## 71. Optional AI assistance

May help explain jargon, structure causal graphs, identify missing information, suggest areas for observation, translate and summarise paths neutrally. Must never vote, rank citizens' worth, silently amend a proposal, invent sources, erase inconvenient hypotheses, issue sanctions alone, fabricate support or make binding public decisions. AI-influenced public content is identified and approved by its author before publication.

## 72. Algorithmic neutrality

Publish the rules governing ordering and recommendation. Prioritise explainable sorting by time, place, theme, status and available evidence. Avoid covert popularity and political-profile ranking. Recommendations, if offered, must be explainable and capable of being disabled.

# PART XIII — SECURITY, PROTECTION AND GOVERNANCE

## 73. Application security

Defend against SQL injection, XSS, CSRF, access-control flaws, tampered requests, privilege escalation, session theft, authentication attacks, replay, double voting, results manipulation, impersonation, leaks, denial of service, vulnerable dependencies and deployment-chain compromise. Verify server authorisation for each sensitive API.

## 74. Authentication

Robust password hashing, appropriate MFA, automated-attempt throttling, session controls, compromised-session revocation, security logs, notifications and secure recovery. Never commit secrets to GitHub.

## 75. Technical administration

Administrators may not secretly create votes, edit results, erase public evidence, alter closed rules, access others' authentication secrets or decide politically for everyone. Separate duties and require independent approvals for critical changes.

## 76. Moderation and coexistence

Address threats, harassment, impersonation, illegal disclosure, proven fraud, automated spam, sabotage and repeated operational disruption. Do not penalise an opinion merely because it is unusual, unpopular or different. Support independent paths and factual evidence checks while respecting people.

## 77. Conduct measures

Warnings, proportionate temporary limits, publication suspension, protection against malicious automation and suspension for sufficiently established misconduct. Every sanction needs a pre-existing rule, documented facts, reasons, limited duration, proportionality, appeal and reconsideration. An isolated allegation never proves fraud.

## 78. Personal-data protection

Named political opinions are **sensitive personal data**. Define controllers, purposes, legal bases, special-category processing conditions, register, notices, consent records where relevant, retention, rectification, erasure, applicable objections, processors, breach response and impact assessment as required. Prepare a DPIA before large-scale real-data deployment. **Do not decentralise identifiable sensitive records until effective legal compliance, including individual rights, has been demonstrated.**

## 79. Technical governance

Separate normal software maintenance from changes to eligibility, counting, public vote disclosure or citizen rights. Sensitive modifications require a documented proposal, rationale, risk assessment, review, tests, governance approval, version publication and rollback plan. No silent democratic rule changes.

# PART XIV — COSTS AND RESOURCES

## 80. Economic principle

Minimise cash expenditure through voluntary contributions **without concealing actual costs**, including equipment, electricity, connectivity, domains, backups, maintenance, replacements, human effort, audits, incidents, accessibility and possible legal expenditure.

## 81. Public financial report

Publish expenditure, contributions, material donations, provided resources, estimated energy, hosting costs, maintenance, trends, legally publishable supporting documents and budget variances. Protect contributors' and suppliers' personal information where required.

## 82. Citizen contributions

Development, translations, audit, documentation, accessibility, testing, volunteer hosting, observation, tools and linguistic review. Contribution never buys a vote or political privilege.

## 83. Licences and intellectual property

Before publishing application code, choose an appropriate free-software licence (consider AGPL for network services), documentation/content licences, open-data reuse rules, contribution policy, author rights and project trademark rules. Publishing this specification **does not itself license future software**.

# PART XV — PERFORMANCE AND QUALITY

## 84. Non-functional objectives

| Measurement | Proposed initial acceptance target |
| --- | --- |
| Public API | p95 below 800 ms |
| Vote recording | p95 below 2 seconds, excluding external verification |
| Live updates | Under 3 seconds in nominal conditions |
| Pilot availability | 99.5% over a defined reporting period |
| Accessibility | WCAG 2.2 AA |
| Vote uniqueness | No accepted duplicate active votes |
| Tally | Identical on independent recalculation |
| Recovery | Documented and successfully tested |
| Synchronisation | No silent data corruption |

These are targets, **not demonstrated performance claims**. Main pages should remain responsive on typical mobile connections.

## 85. Scalability

Test a few dozen fictional citizens, thousands of synthetic users, tens of thousands of sessions, hundreds of thousands of synthetic identities and greater distributed loads. Consider national-scale populations theoretically without assuming every citizen is simultaneously active or claiming national capacity before representative testing.

## 86. Backups and recovery

Scheduled backups, integrity verification, encryption, suitable separation, restoration drills, disaster recovery, tally reconstruction from verified events, corruption handling, documented recovery objectives and acceptable data-loss thresholds. Extra replicas are not a substitute for backups.

## 87. Observability

Availability, application errors, latency, message queues, databases, synchronisation, integrity logs, resource consumption, backup errors and security events. Telemetry is proportionate and must not become political or behavioural surveillance.

# PART XVI — REFERENCE DEMONSTRATOR

## 88. Initial scenario

Three fictional municipalities, or real municipality names paired exclusively with simulated citizens and data, explore reducing car-related nuisances:

- **Path A — Public shuttles:** presumed car dependence; shuttle service; potential reduced traffic and operating expense; monitor traffic, ridership and cost.
- **Path B — Cycling infrastructure:** presumed lack of safe short-distance routes; build cycle paths; monitor motorised journeys, road safety and construction costs.
- **Path C — Rescheduled activity:** presumed concentrated travel peaks; trial voluntary schedule staggering; monitor hourly traffic, satisfaction and disruption.

## 89. Simulation workflow

Create fictional citizens, associate each municipality, build three independent causal paths, open consultations, cast mock votes, display live results, verify signatures and uniqueness, simulate observation periods, introduce an unforeseen effect, allow personal revisions, visualise convergence or divergence, produce municipal assessments and export public evidence. Simulated results must never be passed off as real observations.

## 90. Convergence scenarios

Test **full convergence**, **partial convergence**, **persistent divergence** and **convergence followed by divergence**. None of these outcomes constitutes a judgement about the citizens participating.


# PART XVII — FUNCTIONAL ACCEPTANCE MATRIX

The following **68 verification scenarios** are initial requirements. Tests have not yet been executed or passed.

## 91. Validation scenarios

### A. Territorial references

**DK-001** — Import the 2026 INSEE reference and verify territorial identifiers.

**DK-002** — Verify that each municipality belongs to the correct administrative territory.

**DK-003** — Import a new geographic edition without erasing historical affiliations.

**DK-004** — Handle municipal districts and delegated communes.

**DK-005** — Prevent a strictly local proposal from applying automatically elsewhere.

**DK-006** — Allow another municipality to voluntarily adopt a proposal for examination.

**DK-007** — Record and explain territorial exclusions and adaptations.

**DK-008** — Prevent a change of municipality from granting two votes.

### B. Identity and rights

**DK-009** — Create a fictional citizen with a unique civic identity.

**DK-010** — Reject a duplicate civic identity during a consultation.

**DK-011** — Allow visitors to read public information without signing in.

**DK-012** — Prevent an ineligible visitor from voting.

**DK-013** — Verify that technical administration gives no additional voting power.

**DK-014** — Check consent to voluntary named experimental participation.

**DK-015** — Exercise lawful correction and erasure rights.

**DK-016** — Keep all secrets and private identity documents out of public exports.

### C. Paths and proposals

**DK-017** — Create an independent path.

**DK-018** — Record all twelve causal-tree categories.

**DK-019** — Distinguish hypotheses from observed causes.

**DK-020** — Prevent voting on an incomplete mandatory proposal.

**DK-021** — Attach a source to an observation.

**DK-022** — Revise a path without improperly destroying its lawful version history.

**DK-023** — Display independent paths without ranking people's worth.

**DK-024** — Record an unforeseen consequence with its date.

### D. Consultations and votes

**DK-025** — Open a consultation with frozen wording and rule set.

**DK-026** — Record a FOR vote.

**DK-027** — Record an AGAINST vote.

**DK-028** — Record an ABSTAIN vote.

**DK-029** — Reject a second active vote by the same citizen.

**DK-030** — Apply a permitted vote change without counting the citizen twice.

**DK-031** — Reject votes after the consultation closes.

**DK-032** — Reject silent alterations to a proposal on which voting has begun.

**DK-033** — Verify majority and quorum calculations.

**DK-034** — Handle an empty consultation without a division error.

**DK-035** — Display refreshed results labelled provisional where appropriate.

**DK-036** — Independently recompute the same tally.

### E. Observations and convergence

**DK-037** — Link an experiment to the correct closed consultation.

**DK-038** — Define indicators before the observation period.

**DK-039** — Add clearly labelled synthetic observations.

**DK-040** — Distinguish predictions, observations and causal interpretation.

**DK-041** — Identify partial convergence between independent paths.

**DK-042** — Present persistent divergence without labelling citizens failures.

**DK-043** — Trace a revised personal path after a new observation.

**DK-044** — Produce a territorial assessment with evidence sources and periods.

### F. Security and integrity

**DK-045** — Reject unauthorised editing of another person's vote.

**DK-046** — Detect content alteration after digest creation.

**DK-047** — Detect a break in a chained audit log.

**DK-048** — Reject replay of an already processed critical operation.

**DK-049** — Test defences against injection and cross-site scripting.

**DK-050** — Verify authorisation across every sensitive API.

**DK-051** — Create an integrity alert from a reported anomaly.

**DK-052** — Prevent an unverified allegation from automatically suspending an innocent citizen.

### G. Distributed network

**DK-053** — Synchronise two compatible public data copies.

**DK-054** — Detect divergence between two nodes.

**DK-055** — Reject unsigned events where signatures are mandatory.

**DK-056** — Resume synchronisation after a disconnection.

**DK-057** — Respect configured bandwidth ceilings.

**DK-058** — Immediately stop a volunteer relay on request.

**DK-059** — Prevent unauthorised private records from reaching a public node.

**DK-060** — Restore a verifier node from an intact copy.

### H. Accessibility, governance and finance

**DK-061** — Perform essential operations using only the keyboard.

**DK-062** — Provide a textual equivalent of the causal graph.

**DK-063** — Read the plain-language explanation and glossary.

**DK-064** — Ensure that translations do not silently alter the voting reference version.

**DK-065** — Record an expense with consistent amount and supporting record.

**DK-066** — Inspect lawfully publishable costs and resources.

**DK-067** — Require governance procedure for changes to democratic rules.

**DK-068** — Appeal a moderation restriction or sanction.


## 91 bis. Test acceptance procedure

Each execution record contains test ID, application version, preconditions, input fixture, steps, expected result, actual result, evidence, status (passed, failed, blocked or not run) and incident reference. Presence of a UI screen does not prove the business function works. Cover normal, boundary and adversarial cases.

# PART XVIII — DELIVERY ROADMAP

## 92. Phase 0 — Documentary foundation

Deliverables: founding charter, this specification, glossary, experimental democratic rules, initial risk analysis, reference architecture, test plan and design-decision records. **Acceptance:** resolve material contradictions between charter, voting mechanics, individual rights and technical requirements.

## 93. Phase 1 — Symfony foundation

Deliverables: Symfony 8.1, PHP 8.4, PostgreSQL, Doctrine, migrations, authentication, general interface, geographic reference and startup tests. **Acceptance:** reproducible installation and startup on Arch Linux.

## 94. Phase 2 — Citizens and paths

Fictional accounts, municipal records, causal editor, revisions, observations, supporting evidence and path browser. **Acceptance:** create a complete path and revise it without unlawfully losing its recorded history.

## 95. Phase 3 — Consultations

Proposals, rule sets, eligibility, voting, counting, live updates, closure, results and audit. **Acceptance:** no duplicate active votes; independently reproducible results.

## 96. Phase 4 — Experiments and convergence

Experimental protocols, indicators, observations, assessments, comparisons, convergence visualisations and revisions. **Acceptance:** correctly present convergence, divergence and unforeseen effects.

## 97. Phase 5 — Cryptographic transparency

Event journal, signatures, hashes, checkpoints, exports, independent verifier and integrity alerts. **Acceptance:** tampering within the integrity model is detectable by an independent verifier.

## 98. Phase 6 — Distributed infrastructure

Node synchronisation, volunteer relay agent, bandwidth controls, authorised replication, discrepancy detection, recovery and network simulator. **Acceptance:** consistent data or explicit discrepancy reporting despite the defined test incidents.

## 99. Phase 7 — Preparation for a public experiment

Prerequisites: legal review, DPIA when applicable, security and vote-integrity audits, accessibility verification, governance, appeals, cost reporting, responsibility boundaries and potential local-government partnerships. **No opening date for real-world public consultation is set.**

# PART XIX — GENERAL ACCEPTANCE CRITERIA

## 100. Demonstrator acceptance

The pilot may be described as functional when: application/migrations work; geographic references are coherent; fictional accounts exist; causal paths are versioned; consultations have immutable rule sets; voting is unique and eligible; independent recount matches; observations are sourced; convergence never judges people's worth; integrity journals work; private records are protected; the interface is accessible; critical tests have been run; and the distinction between simulations and legal effects is explicit.

## 101. Blocking failures

Block acceptance if: more than one active vote per person; unauthorised changes to results; undisclosed edits to a live proposal; fictional data misrepresented as real; hidden administrator voting privilege; undetectable log tampering within the defined model; leak of private data; automatic sanction based on a single allegation; unauthorised cross-territorial application; real sensitive processing without legal conditions; essential activities dependent on external AI; or tally algorithms that cannot be independently reproduced.

## 102. Extensibility

Support new territories, languages, causal categories, observation types, visualisations, versioned consultation rules, federation nodes, governance models, provider migration and distributed architecture without a total rewrite. Founding rights and guarantees continue to apply.

# PART XX — DESIGN DECISIONS TO FORMALISE

## 103. Structural decisions

| Reference | Decision needed before real public operation |
| --- | --- |
| DC-01 | Exact scope of public named participation, consent and later rights |
| DC-02 | Civic identity verification mechanism |
| DC-03 | Territorial eligibility and changes of municipality |
| DC-04 | Default simulated quorum, majority and decision rules |
| DC-05 | Rules for changing a vote before closure |
| DC-06 | Collegial governance for democratic rule changes |
| DC-07 | Convergence criteria without personal scoring |
| DC-08 | Separating publishable, verifiable and protected information |
| DC-09 | Federation authorities, consensus and node responsibilities |
| DC-10 | Accounting and volunteer contribution status |
| DC-11 | Licences for software and content |
| DC-12 | Controllers and other legal responsibilities |

None of these choices may be implemented as an undisclosed configuration value.

# PART XXI — REFERENCES AND TRACEABILITY

## 104. References

- [Founding Charter — French, 24 articles](../../CHARTER.md).
- [French requirements — authoritative text](REQUIREMENTS.fr.md).
- [Symfony release and maintenance policy](https://symfony.com/releases).
- [Symfony 8.1](https://symfony.com/releases/8.1).
- [INSEE — Official Geographic Code 2026](https://www.insee.fr/fr/information/8740222).
- [French local-government legislation — Article L2121-29](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006389895).
- [French Electoral Code — Article L59](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006353163).
- [CNIL — Sensitive data](https://www.cnil.fr/fr/definition/donnee-sensible).
- [CNIL — Data protection impact assessments](https://www.cnil.fr/fr/ce-quil-faut-savoir-sur-lanalyse-dimpact-relative-la-protection-des-donnees-aipd).
- [CNIL — Voter personal-data rights](https://www.cnil.fr/fr/respecter-les-droits-des-electeurs-sur-leurs-donnees-personnelles).

Review sources for currency at each revision. This specification is not legal advice.

## 105. Document version management

Each future release records version, date, changes, requirements added/modified/removed, reasons, consequences, technical and legal impact, and tests to revise. Previous decisions cannot disappear without explanation. Publishing documentation does not authorise real-world operation.

# CLOSING DECLARATION

Démos Kratos must not become merely another polling platform or competition between opinions. A vote expresses a decision at a moment in time; observation examines consequences; collective memory preserves lessons; emergent convergence allows independent paths to approach each other freely.

**Let no one own another person's path. Let no one be reduced to their mistakes. Let every consequence teach, and let collective memory illuminate future decisions.**

**DÉMOS KRATOS — Δῆμος Κράτος — Power belongs to the people**

> “Do not impose your path. Illuminate your own, and let the consequences teach everyone.”

---

**End of requirements specification — Version 1.0.**  
**Status:** Fictional design proposal; no real public production deployment has been approved.
