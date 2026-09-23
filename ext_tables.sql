#
# Per-page opt-out for the Enhancely JSON-LD output.
#
CREATE TABLE pages (
	tx_enhancely_hide_jsonld smallint(5) unsigned DEFAULT '0' NOT NULL
);
