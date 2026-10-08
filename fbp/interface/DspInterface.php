<?php

/** Project policies receive (Controller $ctl, string $channel) in their constructor. */
interface DspInterface {
    public function authorizeInsert(array $newRow): void;
    public function authorizeRead(array $request): void;
    public function inspectRead(array $row): DspReadDecision;
    public function authorizeUpdate(array $before, array $after, array $submittedFields): void;
    public function authorizeDelete(array $before): void;
}

/** Optional preparation phase; judgment methods consume only the prepared values. */
interface DspPreparedInterface extends DspInterface {
    public function prepareContext(callable $snapshot): void;
}
