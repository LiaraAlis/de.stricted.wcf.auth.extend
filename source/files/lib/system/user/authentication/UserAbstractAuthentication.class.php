<?php
namespace wcf\system\user\authentication;
use wcf\data\user\group\UserGroup;
use wcf\data\user\UserAction;
use wcf\data\user\User;
use wcf\data\user\UserEditor;
use wcf\data\user\UserProfileAction;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\util\HeaderUtil;
use wcf\util\PasswordUtil;
use wcf\system\database\MySQLDatabase;
use wcf\system\database\PostgreSQLDatabase;
use wcf\util\LDAPUtil;
use wcf\util\UserUtil;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * @author      Jan Altensen (Stricted) / Alexander Pankow (LiaraAlis)
 * @copyright   2013-2014 Jan Altensen (Stricted) / 2014 Alexander Pankow (LiaraAlis)
 * @license     GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package     de.liaraalis.wcf.auth.extended
 * @category    Community Framework
 */
class UserAbstractAuthentication extends DefaultUserAuthentication {
	protected $email = '';
	protected $username = '';

	/**
	 * Checks the given user data.
	 *
	 * @param	string		$username
	 * @param 	string		$password
	 * @return	boolean
	 */
	protected function checkWCFUser($username, $password) {
		if($this->isValidEmail($username))
			$user = User::getUserByEmail($username);
		else
			$user = User::getUserByUsername($username);
		
		if ($user->userID != 0) {
			if ($user->checkPassword($password)) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Checks the given user data.
	 *
	 * @param	string		$loginName
	 * @param 	string		$password
	 * @return	boolean
	 */
	protected function login($loginName, $password) {
		return false;
	}

	/**
	 * @see IUserAuthentication::loginManually()
	 */
	public function loginManually($username, $password, $userClassname = 'wcf\data\user\User') {
		if (!$this->login($username, $password)) {
			throw new UserInputException('password', 'false');
		}
		if(!empty($this->username)) {
			$username = $this->username;
		}
		if($this->isValidEmail($username))
			$user = User::getUserByEmail($username);
		else
			$user = $this->getUserByLogin($username);
		
		if (!$user->userID) {
			// create user
			if(!empty($this->email) && isset($this->email)) {
				$groupIDs = UserGroup::getGroupIDsByType(array(UserGroup::EVERYONE, UserGroup::USERS));
				$languageID = array(LanguageFactory::getInstance()->getDefaultLanguageID());
				$addDefaultGroups = true;
				$saveOptions = array();
				$additionalFields = array();
				$additionalFields['languageID'] = WCF::getLanguage()->languageID;
				$additionalFields['registrationIpAddress'] = WCF::getSession()->ipAddress;
				$data = array(
					'data' => array_merge($additionalFields, array(
						'username' => $username,
						'email' => $this->email,
						'password' => $password,
					)),
					'groups' => $groupIDs,
					'languages' => $languageID,
					'options' => $saveOptions,
					'addDefaultGroups' => $addDefaultGroups
				);
				
				$objectAction = new UserAction(array(), 'create', $data);
				$result = $objectAction->executeAction();
				$user = $result['returnValues'];
			} else {
				throw new UserInputException('password', 'false');
			}
		} else {
			// update user
			$userEditor = new UserEditor($user);
			if (empty($this->email))
				$this->email = $userEditor->email;
			$userEditor->update(array(
				'password' => $password,
				'email' => $this->email
			));
		}
		
		return (get_class($user) == $userClassname ? $user : new $userClassname(null, null, $user));
	}
	
	/**
	 * @see IUserAuthentication::storeAccessData()
	 */
	public function storeAccessData(User $user, $username, $password) {
		HeaderUtil::setCookie('userID', $user->userID, TIME_NOW + 365 * 24 * 3600);
		HeaderUtil::setCookie('password', PasswordUtil::getSaltedHash($password, $user->password), TIME_NOW + 365 * 24 * 3600);
	}

	/**
	 * Validates the cookie password.
	 * 
	 * @param	User		$user
	 * @param	string		$password
	 * @return	boolean
	 */
	protected function checkCookiePassword($user, $password) {
		return $user->checkCookiePassword($password);
	}
	
	/**
	 * Returns true if the given e-mail is a valid address.
	 * 
	 * @param	string		$email
	 * @return	boolean
	 */
	protected function isValidEmail($email) {
		return UserUtil::isValidEmail($email);
	}
}
?>
