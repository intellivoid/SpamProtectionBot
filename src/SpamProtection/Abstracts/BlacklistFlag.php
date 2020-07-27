<?php


    namespace SpamProtection\Abstracts;

    /**
     * Class BlacklistFlag
     * @package SpamProtection\Abstracts
     */
    abstract class BlacklistFlag
    {
        const None = "0x0";

        const Special = "0xSP";

        const Spam = "0xSPAM";

        const PornographicSpam = "0xNSFW";

        const PrivateSpam = "0xPRIVATE";

        const PiracySpam = "0xPIRACY";

        const ChildAbuse = "0xCACP";

        const Raid = "0xRAID";

        const Scam = "0xSCAM";

        const Impersonator = "0xIMPER";

        const BanEvade = "0xEVADE";

        const MassAdding = "0xMASSADD";

        const NameSpam = "0xNAMESPAM";

        const All = [
            self::None,
            self::Special,
            self::Spam,
            self::PornographicSpam,
            self::PrivateSpam,
            self::PiracySpam,
            self::ChildAbuse,
            self::Raid,
            self::Scam,
            self::Impersonator,
            self::BanEvade,
            self::MassAdding,
            self::NameSpam
        ];
    }