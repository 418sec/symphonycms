<?php
	/**
	 * @package toolkit
	 */
	/**
	 * PBKDF2 is a cryptography class for hashing and comparing messages
	 * using the PBKDF2-Algorithm with salting.
	 * This is the most advanced hashing algorithm Symphony provides.
	 */
	Class PBKDF2 extends Cryptography{

		/**
		 * Salt length
		 */
		const SALT_LENGTH = 10;

		/**
		 * Key length
		 */
		const KEY_LENGTH = 40;

		/**
		 * Key length
		 */
		const ITERATIONS = 10000;

		/**
		 * Algorithm to be used
		 */
		const ALGORITHM = 'sha256';

		/**
		 * Uses `PBKDF2` and random salt generation to create a hash based on some input.
		 * Original implementation was under public domain, taken from
		 * http://www.itnewb.com/tutorial/Encrypting-Passwords-with-PHP-for-Storage-Using-the-RSA-PBKDF2-Standard
		 *
		 * @param string $input
		 * the string to be hashed
		 * @param string $salt
		 * an optional salt
		 * @return string
		 * the hashed string
		 */
		public static function hash($input, $salt=NULL, $iterations=NULL, $keylength=NULL){
			if($salt === NULL)
				$salt = self::generateSalt(self::SALT_LENGTH);

			if($iterations === NULL)
				$iterations = self::ITERATIONS;

			if($keylength === NULL)
				$keylength = self::KEY_LENGTH;

			$hashlength = strlen(hash(self::ALGORITHM, null, true));
			$blocks = ceil(self::KEY_LENGTH / $hashlength);
			$key = '';

			for ($block = 1; $block <= $blocks; $block++) {
				$ib = $b = hash_hmac(self::ALGORITHM, $salt . pack('N', $block), $input, true);
				for ($i = 1; $i < $iterations; $i++)
					$ib ^= ($b = hash_hmac(self::ALGORITHM, $b, $input, true));
				$key .= $ib;
			}

			return sprintf("%03d%08d", strlen($salt), $iterations) . $salt . substr(base64_encode($key), 0, $keylength);
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
			$iterations = self::extractIterations($hash);
			$hash = self::extractHash($hash);
			$keylength = strlen($hash);

			return (sprintf("%03d%08d", strlen($salt), $iterations) . $salt . $hash == self::hash($input, $salt, $iterations, $keylength));
		}

		/**
		 * Extracts the hash from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @param int $length
		 * the length of the salt
		 * @return string
		 * the hash
		 */
		public static function extractHash($input){
			$length = self::extractSaltlength($input);
			return substr($input, 16+$length);
		}

		/**
		 * Extracts the salt from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @param int $length
		 * the length of the salt
		 * @return string
		 * the salt
		 */
		public static function extractSalt($input){
			$length = self::extractSaltlength($input);
			return substr($input, 16, $length);
		}

		/**
		 * Extracts the saltlength from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @param int $length
		 * the length of the salt
		 * @return string
		 * the salt
		 */
		public static function extractSaltlength($input){
			return intval(substr($input, 5, 3));
		}

		/**
		 * Extracts the saltlength from a hash/salt-combination
		 *
		 * @param string $input
		 * the hashed string
		 * @param int $length
		 * the length of the salt
		 * @return string
		 * the salt
		 */
		public static function extractIterations($input){
			return intval(substr($input, 8, 8));
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
			$version = substr($hash, 0, 5);
			$length = self::extractSaltlength($hash);
			$iterations = self::extractIterations($hash);
			$keylength = strlen(self::extractHash($hash));

			if($length != self::SALT_LENGTH || $iterations != self::ITERATIONS || $keylength != self::KEY_LENGTH)
				return true;
			else
				return false;
		}
	}
