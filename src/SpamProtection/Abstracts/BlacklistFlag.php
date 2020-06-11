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
    }