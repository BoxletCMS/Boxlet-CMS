<?php

namespace App\Modules\Auth;

use App\Core\Db;
use App\Modules\Mailer\Secret;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use DateTimeImmutable;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

/**
 * Two-step login (SPEC §6, PLAN.md O-4, D-050): a six-digit code from an authenticator app
 * after the password, OPTIONAL and never forced — one admin with forced 2FA and a lost phone
 * is a lost site.
 *
 * Three ways back in, so a lost phone is never a lost site: ten recovery codes, each good
 * once, shown when two-step login is switched on; and, for the owner with none of those,
 * a file named `disable-2fa` put in storage/ over FTP, which switches it off at the next
 * login attempt.
 *
 * The secret is kept sealed with APP_KEY (Secret), like the mail passwords: a copy of the
 * database alone gives nobody the codes. Recovery codes are kept only as password hashes.
 */
final class TwoFactor
{
    public const RESET_FILE = 'disable-2fa';

    /** Codes are accepted one period either side of now: a phone's clock is rarely exact. */
    private const LEEWAY = 29;

    public function __construct(private readonly Db $db, private readonly string $appKey, private readonly ?ClockInterface $clock = null)
    {
    }

    public function enabled(int $adminId): bool
    {
        $row = $this->db->one('SELECT totp_secret FROM admin WHERE id = ?', [$adminId]);

        return $row !== null && is_string($row['totp_secret']) && $row['totp_secret'] !== '';
    }

    /**
     * A new secret, for a setup screen to show until a code proves the app has it: 160
     * bits, the size RFC 4226 recommends and every authenticator app expects — 32 letters
     * to type where the QR code cannot be scanned. The library's own default is 64 bytes,
     * which the setup screen showed as a hundred and three.
     */
    public function newSecret(): string
    {
        return TOTP::generate($this->clock(), 20)->getSecret();
    }

    /** The otpauth:// address an authenticator app reads from the QR code. */
    public function uri(string $secret, string $account, string $issuer): string
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('No secret to show.');
        }
        $totp = TOTP::createFromSecret($secret, $this->clock());
        $totp->setLabel($account !== '' ? $account : 'admin');
        $totp->setIssuer($issuer !== '' ? $issuer : 'Boxlet');

        return $totp->getProvisioningUri();
    }

    /** That address as an SVG QR code, drawn here: no third party ever sees the secret. */
    public static function qr(string $uri): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd())))->writeString($uri);
    }

    public function valid(string $secret, string $code): bool
    {
        $code = preg_replace('~\s+~', '', $code) ?? '';

        return $secret !== ''
            && preg_match('~^\d{6}$~', $code) === 1
            && TOTP::createFromSecret($secret, $this->clock())->verify($code, null, self::LEEWAY);
    }

    /**
     * Switches two-step login on with a secret the owner's app has just proved it holds,
     * and returns the ten recovery codes — shown once, never stored in the clear.
     *
     * @return list<string>
     */
    public function enable(int $adminId, string $secret): array
    {
        $codes = self::newCodes();
        $this->db->query(
            'UPDATE admin SET totp_secret = ?, recovery_codes_json = ? WHERE id = ?',
            [Secret::seal($secret, $this->appKey), self::hashed($codes), $adminId],
        );

        return $codes;
    }

    /**
     * Ten new recovery codes in place of the old ones, which stop working.
     *
     * @return list<string>
     */
    public function renewCodes(int $adminId): array
    {
        $codes = self::newCodes();
        $this->db->query('UPDATE admin SET recovery_codes_json = ? WHERE id = ?', [self::hashed($codes), $adminId]);

        return $codes;
    }

    public function disable(int $adminId): void
    {
        $this->db->query('UPDATE admin SET totp_secret = NULL, recovery_codes_json = NULL WHERE id = ?', [$adminId]);
    }

    /** Whether a code from the app is right for this admin now. */
    public function check(int $adminId, string $code): bool
    {
        $row = $this->db->one('SELECT totp_secret FROM admin WHERE id = ?', [$adminId]);
        $secret = Secret::open((string) ($row['totp_secret'] ?? ''), $this->appKey);

        return $secret !== '' && $this->valid($secret, $code);
    }

    /** Uses up a recovery code if it is one of this admin's; true when it was. */
    public function useRecovery(int $adminId, string $code): bool
    {
        $code = strtolower(preg_replace('~[^a-z0-9]~i', '', $code) ?? '');
        if (strlen($code) !== 10) {
            return false;
        }
        $row = $this->db->one('SELECT recovery_codes_json FROM admin WHERE id = ?', [$adminId]);
        $hashes = json_decode((string) ($row['recovery_codes_json'] ?? ''), true);
        foreach (is_array($hashes) ? $hashes : [] as $i => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($hashes[$i]);
                $this->db->query('UPDATE admin SET recovery_codes_json = ? WHERE id = ?', [json_encode(array_values($hashes), JSON_THROW_ON_ERROR), $adminId]);

                return true;
            }
        }

        return false;
    }

    /** How many recovery codes are left. */
    public function codesLeft(int $adminId): int
    {
        $row = $this->db->one('SELECT recovery_codes_json FROM admin WHERE id = ?', [$adminId]);
        $hashes = json_decode((string) ($row['recovery_codes_json'] ?? ''), true);

        return is_array($hashes) ? count($hashes) : 0;
    }

    /**
     * The FTP way back in (SPEC §6): when storage/disable-2fa exists, two-step login is
     * switched off for every admin and the file removed. Returns whether it did so; the
     * login screen then says so, and warns if the file could not be removed.
     *
     * @return array{done: bool, removed: bool}
     */
    public function resetFromFile(string $storagePath): array
    {
        $file = $storagePath . '/' . self::RESET_FILE;
        if (!is_file($file)) {
            return ['done' => false, 'removed' => false];
        }
        $this->db->query('UPDATE admin SET totp_secret = NULL, recovery_codes_json = NULL');

        return ['done' => true, 'removed' => @unlink($file)];
    }

    /**
     * Ten codes of ten letters and digits, shown as xxxxx-xxxxx; without look-alikes
     * (0/o, 1/l/i), so one copied by hand is copied right.
     *
     * @return list<string>
     */
    private static function newCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes = [];
        for ($n = 0; $n < 10; $n++) {
            $code = '';
            for ($c = 0; $c < 10; $c++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        }

        return $codes;
    }

    /**
     * @param list<string> $codes
     */
    private static function hashed(array $codes): string
    {
        return json_encode(array_map(static fn (string $c): string => password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT), $codes), JSON_THROW_ON_ERROR);
    }

    private function clock(): ClockInterface
    {
        return $this->clock ?? new class () implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };
    }
}
