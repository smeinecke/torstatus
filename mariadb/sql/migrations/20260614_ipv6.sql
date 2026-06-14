USE `torstatus`;

ALTER TABLE `Descriptor1` MODIFY `IP` varchar(45) DEFAULT NULL;
ALTER TABLE `Descriptor2` MODIFY `IP` varchar(45) DEFAULT NULL;
ALTER TABLE `NetworkStatus1` MODIFY `IP` varchar(45) DEFAULT NULL;
ALTER TABLE `NetworkStatus2` MODIFY `IP` varchar(45) DEFAULT NULL;
ALTER TABLE `NetworkStatusSource` MODIFY `IP` varchar(45) DEFAULT NULL;
ALTER TABLE `ORAddresses1` MODIFY `address` varchar(45) NOT NULL;
ALTER TABLE `ORAddresses2` MODIFY `address` varchar(45) NOT NULL;
ALTER TABLE `hostnames` MODIFY `ip` varchar(45) NOT NULL;
