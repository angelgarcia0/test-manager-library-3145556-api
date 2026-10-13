<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02 · Initial schema.
 *
 * ADR-001: the schema is owned by the application's migrations. `infra` only lifts an
 * empty engine, so the schema, the tables, the constraints and the seed all live here.
 *
 * The DDL is written by hand rather than through the schema builder for three reasons:
 *
 *   1. `data-model.md` section 3 forbids DEFAULT on every column. The builder adds them
 *      silently for identity columns.
 *   2. `isbn` is `char(13)`, not `varchar(13)`. The two have different padding semantics
 *      and the model must be reproduced exactly.
 *   3. Article VI: whatever the engine can enforce, the engine enforces. Article X: if the
 *      document and the engine ever disagree, the document is fixed. Raw DDL is the
 *      easiest to audit against the verification queries of section 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('pgsql');

        // The schema belongs here, not in infra. ADR-001.
        $db->statement('CREATE SCHEMA IF NOT EXISTS library');
        $db->statement('SET search_path TO library, public');

        $this->createCategory($db);
        $this->createUser($db);
        $this->createMember($db);
        $this->createBook($db);
        $this->createLoan($db);
        $this->createLoanItem($db);
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('DROP SCHEMA IF EXISTS library CASCADE');
    }

    /**
     * Seed of read-only data. The five category ids are literals from data-model.md
     * section 9 so that a report written today can still be compared with one written
     * after the seed is reloaded.
     */
    private function createCategory(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library.category (
                id   uuid        NOT NULL,
                name varchar(120) NOT NULL,
                CONSTRAINT pk_category PRIMARY KEY (id)
            )
        SQL);

        $db->statement(
            'CREATE UNIQUE INDEX uq_category_name ON library.category (name)'
        );
    }

    /**
     * `user` is a reserved word in SQL; the data model writes library."user" by hand and
     * lets the ORM quote it. No DEFAULT on password_hash: the domain sets the value.
     */
    private function createUser(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library."user" (
                id            uuid         NOT NULL,
                username      varchar(120) NOT NULL,
                password_hash varchar(512) NOT NULL,
                role          varchar(40)  NOT NULL,
                CONSTRAINT pk_user PRIMARY KEY (id)
            )
        SQL);

        $db->statement('CREATE UNIQUE INDEX uq_user_username ON library."user" (username)');

        // ck_user_role is marked "pendiente (T-18)" in data-model.md section 4. It is
        // deliberately absent here: the document must not promise what is not applied.
    }

    private function createMember(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library.member (
                id              uuid         NOT NULL,
                full_name       varchar(200) NOT NULL,
                document_number varchar(30)  NOT NULL,
                CONSTRAINT pk_member PRIMARY KEY (id)
            )
        SQL);

        $db->statement(
            'CREATE UNIQUE INDEX uq_member_document_number ON library.member (document_number)'
        );
    }

    /**
     * CA-03.2 and ADR-003: the isbn index is PARTIAL over active books, so an isbn that
     * was retired can be registered again. A plain unique index would break that.
     */
    private function createBook(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library.book (
                id               uuid          NOT NULL,
                title            varchar(250)  NOT NULL,
                author           varchar(200)  NOT NULL,
                isbn             char(13)      NOT NULL,
                category_id      uuid          NOT NULL,
                total_copies     integer       NOT NULL,
                available_copies integer       NOT NULL,
                cover_key        varchar(512)  NULL,
                deleted_at       timestamptz   NULL,
                CONSTRAINT pk_book PRIMARY KEY (id),
                CONSTRAINT fk_book_category FOREIGN KEY (category_id)
                    REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT ck_book_available_range CHECK (
                    available_copies >= 0 AND available_copies <= total_copies
                )
            )
        SQL);

        $db->statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_book_isbn_active
                ON library.book (isbn) WHERE deleted_at IS NULL
        SQL);

        // Access index for Q1: browse by category, order by title, active only.
        $db->statement(<<<'SQL'
            CREATE INDEX ix_book_category_title_active
                ON library.book (category_id, title) WHERE deleted_at IS NULL
        SQL);

        // ck_book_total_positive is marked "pendiente (T-18)" in data-model.md section 4.
    }

    private function createLoan(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library.loan (
                id                 uuid        NOT NULL,
                member_id          uuid        NOT NULL,
                loaned_by_user_id  uuid        NOT NULL,
                loaned_at          timestamptz NOT NULL,
                CONSTRAINT pk_loan PRIMARY KEY (id),
                CONSTRAINT fk_loan_member FOREIGN KEY (member_id)
                    REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_loaned_by_user FOREIGN KEY (loaned_by_user_id)
                    REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            )
        SQL);

        $db->statement('CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at)');

        // No `status` column. Article VII: the state of a loan is calculated, never stored.
    }

    /**
     * FK-2 is CASCADE by composition, and it never fires because loans are never deleted
     * (RN-5). FK-3 is RESTRICT as a last barrier so a manual DELETE cannot orphan history.
     * `category_name` has NO foreign key on purpose (ADR-004).
     */
    private function createLoanItem(\Illuminate\Database\Connection $db): void
    {
        $db->statement(<<<'SQL'
            CREATE TABLE library.loan_item (
                id            uuid         NOT NULL,
                loan_id       uuid         NOT NULL,
                book_id       uuid         NOT NULL,
                book_title    varchar(250) NOT NULL,
                isbn          char(13)     NOT NULL,
                category_name varchar(120) NOT NULL,
                due_at        timestamptz  NOT NULL,
                returned_at   timestamptz  NULL,
                CONSTRAINT pk_loan_item PRIMARY KEY (id),
                CONSTRAINT fk_loan_item_loan FOREIGN KEY (loan_id)
                    REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_item_book FOREIGN KEY (book_id)
                    REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            )
        SQL);

        $db->statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_loan_item_loan_book
                ON library.loan_item (loan_id, book_id)
        SQL);

        $db->statement('CREATE INDEX ix_loan_item_book ON library.loan_item (book_id)');

        // Q8: the overdue list. Partial, because a returned line is never overdue.
        $db->statement(<<<'SQL'
            CREATE INDEX ix_loan_item_due_at_open
                ON library.loan_item (due_at) WHERE returned_at IS NULL
        SQL);

        // No `days_late` column. Article VII.
    }
};
