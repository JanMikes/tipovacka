<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * S01 domain rename: Tournament -> MatchSource, Group -> Competition.
 *
 * Hand-written rename migration (documented exception to the generated-only rule):
 * pure RENAME statements for tables, columns, indexes and constraints — no data loss.
 */
final class Version20260718120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename tournaments->match_sources and user_groups->competitions (incl. FK columns, indexes, constraints)';
    }

    public function up(Schema $schema): void
    {
        // --- Table renames -------------------------------------------------
        $this->addSql('ALTER TABLE tournaments RENAME TO match_sources');
        $this->addSql('ALTER TABLE user_groups RENAME TO competitions');
        $this->addSql('ALTER TABLE group_invitations RENAME TO competition_invitations');
        $this->addSql('ALTER TABLE group_join_requests RENAME TO competition_join_requests');
        $this->addSql('ALTER TABLE group_match_settings RENAME TO competition_match_settings');
        $this->addSql('ALTER TABLE tournament_rule_configurations RENAME TO match_source_rule_configurations');

        // --- Column renames ------------------------------------------------
        $this->addSql('ALTER TABLE competitions RENAME COLUMN tournament_id TO match_source_id');
        $this->addSql('ALTER TABLE competition_invitations RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE competition_join_requests RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE competition_match_settings RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME COLUMN tournament_id TO match_source_id');
        $this->addSql('ALTER TABLE guesses RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE memberships RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions RENAME COLUMN group_id TO competition_id');
        $this->addSql('ALTER TABLE sport_matches RENAME COLUMN tournament_id TO match_source_id');

        // --- Primary key constraint renames --------------------------------
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT tournaments_pkey TO match_sources_pkey');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT user_groups_pkey TO competitions_pkey');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT group_invitations_pkey TO competition_invitations_pkey');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT group_join_requests_pkey TO competition_join_requests_pkey');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT group_match_settings_pkey TO competition_match_settings_pkey');
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME CONSTRAINT tournament_rule_configurations_pkey TO match_source_rule_configurations_pkey');

        // --- Named index renames -------------------------------------------
        $this->addSql('ALTER INDEX idx_tournaments_public_active RENAME TO IDX_match_sources_public_active');
        $this->addSql('ALTER INDEX idx_tournaments_owner_visibility RENAME TO IDX_match_sources_owner_visibility');
        $this->addSql('ALTER INDEX idx_user_groups_tournament RENAME TO IDX_competitions_match_source');
        $this->addSql('ALTER INDEX idx_user_groups_owner RENAME TO IDX_competitions_owner');
        $this->addSql('ALTER INDEX uidx_user_groups_pin RENAME TO UIDX_competitions_pin');
        $this->addSql('ALTER INDEX uidx_user_groups_shareable_link_token RENAME TO UIDX_competitions_shareable_link_token');
        $this->addSql('ALTER INDEX idx_group_invitations_group RENAME TO IDX_competition_invitations_competition');
        $this->addSql('ALTER INDEX uidx_group_invitations_token RENAME TO UIDX_competition_invitations_token');
        $this->addSql('ALTER INDEX idx_join_requests_group_decided RENAME TO IDX_join_requests_competition_decided');
        $this->addSql('ALTER INDEX uidx_group_match_settings_group_match RENAME TO UIDX_competition_match_settings_competition_match');
        $this->addSql('ALTER INDEX idx_guesses_match_group RENAME TO IDX_guesses_match_competition');
        $this->addSql('ALTER INDEX idx_guesses_user_group RENAME TO IDX_guesses_user_competition');
        $this->addSql('ALTER INDEX idx_memberships_group_active RENAME TO IDX_memberships_competition_active');
        $this->addSql('ALTER INDEX idx_sport_matches_tournament_kickoff RENAME TO IDX_sport_matches_match_source_kickoff');
        $this->addSql('ALTER INDEX uidx_rule_config_tournament_rule RENAME TO UIDX_rule_config_match_source_rule');

        // --- Doctrine-hashed FK index renames (names derive from table + column) ---
        $this->addSql('ALTER INDEX idx_e4bcfac37e3c61f9 RENAME TO IDX_A5FB3A9D7E3C61F9');
        $this->addSql('ALTER INDEX idx_e4bcfac3ac78bcf8 RENAME TO IDX_A5FB3A9DAC78BCF8');
        $this->addSql('ALTER INDEX idx_953f224d33d1a3e7 RENAME TO IDX_A7DD463D8C8D50CA');
        $this->addSql('ALTER INDEX idx_953f224d7e3c61f9 RENAME TO IDX_A7DD463D7E3C61F9');
        $this->addSql('ALTER INDEX idx_69f0f6fb79f4f04 RENAME TO IDX_B86BFD0B79F4F04');
        $this->addSql('ALTER INDEX idx_690cdc20a76ed395 RENAME TO IDX_C130D0C2A76ED395');
        $this->addSql('ALTER INDEX idx_690cdc20e26b496b RENAME TO IDX_C130D0C2E26B496B');
        $this->addSql('ALTER INDEX idx_690cdc20fe54d947 RENAME TO IDX_C130D0C27B39D312');
        $this->addSql('ALTER INDEX idx_a0b2b3211c1c536c RENAME TO IDX_EE1E0C791C1C536C');
        $this->addSql('ALTER INDEX idx_a0b2b321fe54d947 RENAME TO IDX_EE1E0C797B39D312');
        $this->addSql('ALTER INDEX idx_395b8cc533d1a3e7 RENAME TO IDX_1A48A5448C8D50CA');
        $this->addSql('ALTER INDEX idx_27fbaf45fe54d947 RENAME TO IDX_27FBAF457B39D312');
        $this->addSql('ALTER INDEX idx_865a4776fe54d947 RENAME TO IDX_865A47767B39D312');
        $this->addSql('ALTER INDEX idx_bfc3191fe54d947 RENAME TO IDX_BFC31917B39D312');
        $this->addSql('ALTER INDEX idx_a793591033d1a3e7 RENAME TO IDX_A79359108C8D50CA');

        // --- FK constraint renames (same hash scheme, FK_ prefix) ----------
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT fk_e4bcfac37e3c61f9 TO FK_A5FB3A9D7E3C61F9');
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT fk_e4bcfac3ac78bcf8 TO FK_A5FB3A9DAC78BCF8');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT fk_953f224d33d1a3e7 TO FK_A7DD463D8C8D50CA');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT fk_953f224d7e3c61f9 TO FK_A7DD463D7E3C61F9');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT fk_69f0f6fb79f4f04 TO FK_B86BFD0B79F4F04');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT fk_69f0f6ffe54d947 TO FK_B86BFD07B39D312');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_690cdc20a76ed395 TO FK_C130D0C2A76ED395');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_690cdc20e26b496b TO FK_C130D0C2E26B496B');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_690cdc20fe54d947 TO FK_C130D0C27B39D312');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT fk_a0b2b3211c1c536c TO FK_EE1E0C791C1C536C');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT fk_a0b2b321fe54d947 TO FK_EE1E0C797B39D312');
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME CONSTRAINT fk_395b8cc533d1a3e7 TO FK_1A48A5448C8D50CA');
        $this->addSql('ALTER TABLE guesses RENAME CONSTRAINT fk_27fbaf45fe54d947 TO FK_27FBAF457B39D312');
        $this->addSql('ALTER TABLE memberships RENAME CONSTRAINT fk_865a4776fe54d947 TO FK_865A47767B39D312');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions RENAME CONSTRAINT fk_bfc3191fe54d947 TO FK_BFC31917B39D312');
        $this->addSql('ALTER TABLE sport_matches RENAME CONSTRAINT fk_a793591033d1a3e7 TO FK_A79359108C8D50CA');
    }

    public function down(Schema $schema): void
    {
        // --- FK constraint renames (reverse) -------------------------------
        $this->addSql('ALTER TABLE sport_matches RENAME CONSTRAINT fk_a79359108c8d50ca TO FK_A793591033D1A3E7');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions RENAME CONSTRAINT fk_bfc31917b39d312 TO FK_BFC3191FE54D947');
        $this->addSql('ALTER TABLE memberships RENAME CONSTRAINT fk_865a47767b39d312 TO FK_865A4776FE54D947');
        $this->addSql('ALTER TABLE guesses RENAME CONSTRAINT fk_27fbaf457b39d312 TO FK_27FBAF45FE54D947');
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME CONSTRAINT fk_1a48a5448c8d50ca TO FK_395B8CC533D1A3E7');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT fk_ee1e0c797b39d312 TO FK_A0B2B321FE54D947');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT fk_ee1e0c791c1c536c TO FK_A0B2B3211C1C536C');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_c130d0c27b39d312 TO FK_690CDC20FE54D947');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_c130d0c2e26b496b TO FK_690CDC20E26B496B');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT fk_c130d0c2a76ed395 TO FK_690CDC20A76ED395');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT fk_b86bfd07b39d312 TO FK_69F0F6FFE54D947');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT fk_b86bfd0b79f4f04 TO FK_69F0F6FB79F4F04');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT fk_a7dd463d7e3c61f9 TO FK_953F224D7E3C61F9');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT fk_a7dd463d8c8d50ca TO FK_953F224D33D1A3E7');
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT fk_a5fb3a9dac78bcf8 TO FK_E4BCFAC3AC78BCF8');
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT fk_a5fb3a9d7e3c61f9 TO FK_E4BCFAC37E3C61F9');

        // --- Doctrine-hashed FK index renames (reverse) --------------------
        $this->addSql('ALTER INDEX idx_a79359108c8d50ca RENAME TO IDX_A793591033D1A3E7');
        $this->addSql('ALTER INDEX idx_bfc31917b39d312 RENAME TO IDX_BFC3191FE54D947');
        $this->addSql('ALTER INDEX idx_865a47767b39d312 RENAME TO IDX_865A4776FE54D947');
        $this->addSql('ALTER INDEX idx_27fbaf457b39d312 RENAME TO IDX_27FBAF45FE54D947');
        $this->addSql('ALTER INDEX idx_1a48a5448c8d50ca RENAME TO IDX_395B8CC533D1A3E7');
        $this->addSql('ALTER INDEX idx_ee1e0c797b39d312 RENAME TO IDX_A0B2B321FE54D947');
        $this->addSql('ALTER INDEX idx_ee1e0c791c1c536c RENAME TO IDX_A0B2B3211C1C536C');
        $this->addSql('ALTER INDEX idx_c130d0c27b39d312 RENAME TO IDX_690CDC20FE54D947');
        $this->addSql('ALTER INDEX idx_c130d0c2e26b496b RENAME TO IDX_690CDC20E26B496B');
        $this->addSql('ALTER INDEX idx_c130d0c2a76ed395 RENAME TO IDX_690CDC20A76ED395');
        $this->addSql('ALTER INDEX idx_b86bfd0b79f4f04 RENAME TO IDX_69F0F6FB79F4F04');
        $this->addSql('ALTER INDEX idx_a7dd463d7e3c61f9 RENAME TO IDX_953F224D7E3C61F9');
        $this->addSql('ALTER INDEX idx_a7dd463d8c8d50ca RENAME TO IDX_953F224D33D1A3E7');
        $this->addSql('ALTER INDEX idx_a5fb3a9dac78bcf8 RENAME TO IDX_E4BCFAC3AC78BCF8');
        $this->addSql('ALTER INDEX idx_a5fb3a9d7e3c61f9 RENAME TO IDX_E4BCFAC37E3C61F9');

        // --- Named index renames (reverse) ---------------------------------
        $this->addSql('ALTER INDEX uidx_rule_config_match_source_rule RENAME TO UIDX_rule_config_tournament_rule');
        $this->addSql('ALTER INDEX idx_sport_matches_match_source_kickoff RENAME TO IDX_sport_matches_tournament_kickoff');
        $this->addSql('ALTER INDEX idx_memberships_competition_active RENAME TO IDX_memberships_group_active');
        $this->addSql('ALTER INDEX idx_guesses_user_competition RENAME TO IDX_guesses_user_group');
        $this->addSql('ALTER INDEX idx_guesses_match_competition RENAME TO IDX_guesses_match_group');
        $this->addSql('ALTER INDEX uidx_competition_match_settings_competition_match RENAME TO UIDX_group_match_settings_group_match');
        $this->addSql('ALTER INDEX idx_join_requests_competition_decided RENAME TO IDX_join_requests_group_decided');
        $this->addSql('ALTER INDEX uidx_competition_invitations_token RENAME TO UIDX_group_invitations_token');
        $this->addSql('ALTER INDEX idx_competition_invitations_competition RENAME TO IDX_group_invitations_group');
        $this->addSql('ALTER INDEX uidx_competitions_shareable_link_token RENAME TO UIDX_user_groups_shareable_link_token');
        $this->addSql('ALTER INDEX uidx_competitions_pin RENAME TO UIDX_user_groups_pin');
        $this->addSql('ALTER INDEX idx_competitions_owner RENAME TO IDX_user_groups_owner');
        $this->addSql('ALTER INDEX idx_competitions_match_source RENAME TO IDX_user_groups_tournament');
        $this->addSql('ALTER INDEX idx_match_sources_owner_visibility RENAME TO IDX_tournaments_owner_visibility');
        $this->addSql('ALTER INDEX idx_match_sources_public_active RENAME TO IDX_tournaments_public_active');

        // --- Primary key constraint renames (reverse) ----------------------
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME CONSTRAINT match_source_rule_configurations_pkey TO tournament_rule_configurations_pkey');
        $this->addSql('ALTER TABLE competition_match_settings RENAME CONSTRAINT competition_match_settings_pkey TO group_match_settings_pkey');
        $this->addSql('ALTER TABLE competition_join_requests RENAME CONSTRAINT competition_join_requests_pkey TO group_join_requests_pkey');
        $this->addSql('ALTER TABLE competition_invitations RENAME CONSTRAINT competition_invitations_pkey TO group_invitations_pkey');
        $this->addSql('ALTER TABLE competitions RENAME CONSTRAINT competitions_pkey TO user_groups_pkey');
        $this->addSql('ALTER TABLE match_sources RENAME CONSTRAINT match_sources_pkey TO tournaments_pkey');

        // --- Column renames (reverse) --------------------------------------
        $this->addSql('ALTER TABLE sport_matches RENAME COLUMN match_source_id TO tournament_id');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE memberships RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE guesses RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME COLUMN match_source_id TO tournament_id');
        $this->addSql('ALTER TABLE competition_match_settings RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE competition_join_requests RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE competition_invitations RENAME COLUMN competition_id TO group_id');
        $this->addSql('ALTER TABLE competitions RENAME COLUMN match_source_id TO tournament_id');

        // --- Table renames (reverse) ---------------------------------------
        $this->addSql('ALTER TABLE match_source_rule_configurations RENAME TO tournament_rule_configurations');
        $this->addSql('ALTER TABLE competition_match_settings RENAME TO group_match_settings');
        $this->addSql('ALTER TABLE competition_join_requests RENAME TO group_join_requests');
        $this->addSql('ALTER TABLE competition_invitations RENAME TO group_invitations');
        $this->addSql('ALTER TABLE competitions RENAME TO user_groups');
        $this->addSql('ALTER TABLE match_sources RENAME TO tournaments');
    }
}
