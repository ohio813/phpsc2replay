#ifndef SC2REPLAY_PLAYER_H
#define SC2REPLAY_PLAYER_H

#include <string>
#include <map>
#include <vector>
#include <boost/fusion/adapted/struct/adapt_struct.hpp>

#include "types.h"

#include <ostream>

namespace sc2replay
{
    struct Player
    {
        explicit Player(const std::string& sn = "", const std::string& r = "")
            : shortName_(sn), race_(r)
        {}

        const std::string& getShortName() const { return shortName_; }
        const std::string& getRace() const { return race_; }
        int getUID() const { return getAttribute<int>(0x8u); }

        bool operator==(const Player& o) const { return shortName_ == o.shortName_; }

        bool operator!() const { return isValid(); }
        bool isValid() const { return shortName_.size() && race_.size(); }

        int getTeam() const { return getAttribute<int>(0x10u); }
        int getColor() const;
        std::string getColorAsString() const;

        std::string shortName_;
        std::string race_;

        typedef std::vector<std::pair<uint8_t, int> > attributes_type;
        attributes_type attributes_;

        friend std::ostream&  
        operator<<(std::ostream& out, const sc2replay::Player& p)
            {
                out << p.shortName_;
                return out;
            }

    private:
        template <typename K>
        int getAttribute(K key) const;
    };

    template <typename K>
    int Player::getAttribute(K key) const
    {
        for (attributes_type::const_iterator it = attributes_.begin();
             it != attributes_.end(); ++it)
        {
            if (it->first == static_cast<uint8_t>(key))
                return it->second/2;
        }
        return 0;
    }

    typedef std::vector<Player>   Players;

}


BOOST_FUSION_ADAPT_STRUCT(
    sc2replay::Player,
    (std::string, shortName_)
    (std::string, race_)
    (sc2replay::Player::attributes_type, attributes_))


#endif
// Local Variables:
// mode:c++
// c-file-style: "stroustrup"
// end:

