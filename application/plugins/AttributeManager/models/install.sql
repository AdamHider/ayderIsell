/**
 * Author:  Baycik
 * Created: May 4, 2017
 */

CREATE TABLE `attribute_values` (
  `attribute_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `attribute_value` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`product_id`,`attribute_id`),
  KEY `fk_attribute_list_idx` (`attribute_id`),
  CONSTRAINT `fk_attribute_list` FOREIGN KEY (`attribute_id`) REFERENCES `attribute_list` (`attribute_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_list` FOREIGN KEY (`product_id`) REFERENCES `prod_list` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `attribute_list` (
  `attribute_id` int(11) NOT NULL AUTO_INCREMENT,
  `attribute_name` varchar(255) DEFAULT NULL,
  `attribute_unit` varchar(225) DEFAULT NULL,
  PRIMARY KEY (`attribute_id`),
  UNIQUE KEY `attribute_id_UNIQUE` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
