-- Migration: add missing indexes identified by code analysis
-- Date: 2026-06-14
--
-- Adds indexes used by queries in the updater and web frontend.
-- Descriptor.LastDescriptorPublished: used by fix_future_timestamps()
-- Bandwidth.fingerprint: lookup key (tinytext, requires prefix length)

USE `torstatus`;

-- Descriptor tables: LastDescriptorPublished is filtered in fix_future_timestamps()
ALTER TABLE `Descriptor1`
  ADD KEY IF NOT EXISTS `Index_LastDescriptorPublished` (`LastDescriptorPublished`);

ALTER TABLE `Descriptor2`
  ADD KEY IF NOT EXISTS `Index_LastDescriptorPublished` (`LastDescriptorPublished`);

-- Bandwidth tables: fingerprint is the natural lookup key
ALTER TABLE `Bandwidth1`
  ADD KEY IF NOT EXISTS `fingerprint` (`fingerprint`(40));

ALTER TABLE `Bandwidth2`
  ADD KEY IF NOT EXISTS `fingerprint` (`fingerprint`(40));
