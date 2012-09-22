<?php
	/**
	 * @package toolkit
	 */
	/**
	 * SHA1 is a cryptography class for hashing and comparing messages
	 * using the SHA1-Algorithm with salting.
	 *
	 * @deprecated This code is regarded as insecure and exists only for backwards-compatibility-purposes.
	 * It should not be used when writing new password-related features.
	 */
	Class SSHA1 extends Cryptography{

		/**
		 * Salt length
		 */
		const SALT_LENGTH = 20;

		/**
		 * Prefix to identify the algorithm used
		 */
		const PREFIX = 'SSHA1Xv1';

		/**
		 * Uses `SHA1` and random salt generation to create a hash based on some input
		 *
		 * @param string $input
		 * the string to be hashed
		 * @param string $salt
		 * an optional salt
		 * @return string
		 * the hashed string
		 */
		public static function hash($input, $salt=NULL){
			if($salt === NULL)
				$salt = self::generateSalt(self::SALT_LENGTH);

			return self::PREFIX . "|" . $salt . "|" . sha1($salt . $input);
		}

		/**
		 * Compares a given hash with a cleantext password. Also extracts the salt
		 * from the hash.
		 *
		 * @param string $input
		 * the cleartext password
		 * @param string $hash
		 * the hash the password should be checked against
		 * @return bool
		 * the result of the comparison
		 */
		public static function compare($input, $hash){
			$salt = self::extractSalt($hash);

			return $hash == self::hash($input, $salt);
		}

		/**
		 * Extracts the hash from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @return string
		 * the hash
		 */
		public static function extractHash($input){
			$data = explode("|",$input, 3);
			return $data[2];
		}

		/**
		 * Extracts the salt from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @return string
		 * the salt
		 */
		public static function extractSalt($input){
			$data = explode("|",$input, 3);
			return $data[1];
		}

		/**
		 * Extracts the saltlength from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @return int
		 * the saltlength
		 */
		public static function extractSaltlength($input){
			return strlen(self::extractSalt($input));
		}

		/**
		 * Checks if provided hash has been computed by most recent algorithm
		 * returns true if otherwise
		 *
		 * @param string $hash
		 * the hash to be checked
		 * @return bool
		 * whether the hash should be re-computed
		 */
		public static function requiresMigration($hash){
			$version = substr($hash, 0, 8);
			$length = self::extractSaltlength($hash);

			if($length != self::SALT_LENGTH)
				return true;
			else
				return false;
		}
	}
