# Atomic delivery batches

A publication writes one immutable event body and its complete subscriber delivery set in a single database transaction. The dispatcher no longer caches spl_object_id values. Each explicit publication is independent, even for the same PHP object. Legacy dispatch(event, subscriber) calls remain supported as independent one-subscriber batches.

The publisher collects asynchronous subscribers after executing synchronous/debounced routing. The optional AsyncEnqueueFilterInterface provides a side-effect-free preflight; handlers must still validate current state at execution. Errors in preflight or publication propagate to callers. There is no synchronous fallback: it could conceal delivery failures or repeat effects.

The queue uses a conditional pending-to-processing UPDATE; only the winner reads the event body. Pending selection and event completion checks have explicit indexes installed through EventQueueSchema. Completion cleanup uses bulk deletes inside a transaction. Deployments must stop old publishers/workers during the library switch: an old publisher can still append deliveries after cleanup.

Compact Event subclasses call parent::__construct(captureUser: false). The worker resolves their actor ID and restores the previous identity after each message. Legacy serialized events with captured users remain readable.

This is atomic queue publication, not a general transactional outbox for arbitrary application writes. Callers requiring entity-write/event atomicity must wrap both in the same database transaction. External side effects still require idempotency; no exactly-once guarantee is made.

Verification: tests/Integration/AtomicEventQueueTest.php runs with a real development Bitrix bootstrap, rolls back its data, and covers batch normalization, object-ID reuse, rollback of partial publication, deferred retries and completion cleanup. Default standalone tests skip this integration suite.
