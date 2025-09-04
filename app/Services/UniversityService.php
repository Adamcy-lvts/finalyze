<?php

namespace App\Services;

class UniversityService
{
    /**
     * Map of university codes to full names (without abbreviations)
     */
    private static array $universityMap = [
        // Federal Universities
        'abu' => 'Ahmadu Bello University, Zaria',
        'ui' => 'University of Ibadan',
        'unn' => 'University of Nigeria, Nsukka',
        'oau' => 'Obafemi Awolowo University, Ile-Ife',
        'unilag' => 'University of Lagos',
        'unical' => 'University of Calabar',
        'unijos' => 'University of Jos',
        'unimaid' => 'University of Maiduguri',
        'uniben' => 'University of Benin',
        'uniport' => 'University of Port Harcourt',
        'buk' => 'Bayero University, Kano',
        'uniuyo' => 'University of Uyo',
        'uniilorin' => 'University of Ilorin',
        'futminna' => 'Federal University of Technology, Minna',
        'futa' => 'Federal University of Technology, Akure',
        'futo' => 'Federal University of Technology, Owerri',
        'modibbo' => 'Modibbo Adama University of Technology, Yola',
        'uniabuja' => 'University of Abuja',
        'nda' => 'Nigerian Defence Academy, Kaduna',
        'funaab' => 'Federal University of Agriculture, Abeokuta',
        'fudutsinma' => 'Federal University, Dutsin-Ma',
        'fugashua' => 'Federal University, Gashua',
        'fukashere' => 'Federal University, Kashere',
        'fulafia' => 'Federal University, Lafia',
        'fulokoja' => 'Federal University, Lokoja',
        'funai' => 'Federal University, Ndufu-Alike',
        'fuotuoke' => 'Federal University, Otuoke',
        'fuoye' => 'Federal University, Oye-Ekiti',
        'fuwukari' => 'Federal University, Wukari',

        // State Universities
        'lasu' => 'Lagos State University',
        'aaua' => 'Adekunle Ajasin University, Akungba',
        'adsu' => 'Adamawa State University, Mubi',
        'aksu' => 'Akwa Ibom State University',
        'ambrose' => 'Ambrose Alli University, Ekpoma',
        'ansu' => 'Anambra State University, Uli',
        'basu' => 'Bauchi State University, Gadau',
        'bsu' => 'Benue State University, Makurdi',
        'bosu' => 'Bornu State University, Maiduguri',
        'crutech' => 'Cross River University of Technology',
        'delsu' => 'Delta State University, Abraka',
        'ebsu' => 'Ebonyi State University, Abakaliki',
        'edsu' => 'Edo State University, Uzairue',
        'eksu' => 'Ekiti State University, Ado-Ekiti',
        'esut' => 'Enugu State University of Science and Technology',
        'fcuotuoke' => 'Federal College of Education (Technical), Otuoke',
        'fuam' => 'Federal University of Agriculture, Makurdi',
        'gombe' => 'Gombe State University',
        'imsu' => 'Imo State University, Owerri',
        'jabu' => 'Joseph Ayo Babalola University, Ikeji-Arakeji',
        'kasu' => 'Kaduna State University',
        'kogi' => 'Kogi State University, Anyigba',
        'kwasu' => 'Kwara State University, Malete',
        'lautech' => 'Ladoke Akintola University of Technology, Ogbomoso',
        'mouau' => 'Michael Okpara University of Agriculture, Umudike',
        'nasarawa' => 'Nasarawa State University, Keffi',
        'noun' => 'National Open University of Nigeria',
        'oou' => 'Olabisi Onabanjo University, Ago-Iwoye',
        'osun' => 'Osun State University, Osogbo',
        'plasu' => 'Plateau State University, Bokkos',
        'rsust' => 'Rivers State University of Science and Technology',
        'sokoto' => 'Sokoto State University',
        'tasued' => 'Tai Solarin University of Education, Ijagun',
        'unizik' => 'Nnamdi Azikiwe University, Awka',
        'ysu' => 'Yobe State University, Damaturu',
        'zamfara' => 'Zamfara State University',

        // Private Universities
        'cu' => 'Covenant University, Ota',
        'babcock' => 'Babcock University, Ilishan-Remo',
        'aun' => 'American University of Nigeria, Yola',
        'adeleke' => 'Adeleke University, Ede',
        'afe_babalola' => 'Afe Babalola University, Ado-Ekiti',
        'ajayi_crowther' => 'Ajayi Crowther University, Oyo',
        'al_qalam' => 'Al-Qalam University, Katsina',
        'al_hikmah' => 'Al-Hikmah University, Ilorin',
        'baze' => 'Baze University, Abuja',
        'bells' => 'Bells University of Technology, Ota',
        'bingham' => 'Bingham University, Karu',
        'bowen' => 'Bowen University, Iwo',
        'caleb' => 'Caleb University, Lagos',
        'crawford' => 'Crawford University, Igbesa',
        'crescent' => 'Crescent University, Abeokuta',
        'elizade' => 'Elizade University, Ilara-Mokin',
        'fountain' => 'Fountain University, Osogbo',
        'igbinedion' => 'Igbinedion University, Okada',
        'landmark' => 'Landmark University, Omu-Aran',
        'lead_city' => 'Lead City University, Ibadan',
        'madonna' => 'Madonna University, Okija',
        'mcpherson' => 'McPherson University, Seriki-Sotayo',
        'mountain_top' => 'Mountain Top University, Ibafo',
        'nile' => 'Nile University of Nigeria, Abuja',
        'oduduwa' => 'Oduduwa University, Ipetumodu',
        'pan_atlantic' => 'Pan-Atlantic University, Lagos',
        'paul' => 'Paul University, Awka',
        'redeemers' => 'Redeemer\'s University, Ede',
        'rhema' => 'Rhema University, Obeama-Asa',
        'salem' => 'Salem University, Lokoja',
        'samuel_adegboyega' => 'Samuel Adegboyega University, Ogwa',
        'southwestern' => 'Southwestern University, Okun-Owa',
        'summit' => 'Summit University, Offa',
        'veritas' => 'Veritas University, Abuja',
        'wellspring' => 'Wellspring University, Evbuobanosa',
        'western_delta' => 'Western Delta University, Oghara',
    ];

    /**
     * Get full university name from code
     */
    public static function getFullName(string $universityCode): string
    {
        return self::$universityMap[$universityCode] ?? $universityCode;
    }

    /**
     * Get all university mappings
     */
    public static function getAllMappings(): array
    {
        return self::$universityMap;
    }
}
