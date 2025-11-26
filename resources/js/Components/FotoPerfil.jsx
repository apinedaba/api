import { getThumbnailUrl } from "@/utils/globalFunctions";

export default function FotoPerfil({ image, alt, name, className, detail = false }) {
    const defaultStyles = "w-32 h-32 w-full  bg-blue-two p-2 border-2 border-white rounded-full text-lg"
    function getInitials(name) {
        if (!name) return "";

        const words = name.trim().split(" ");
        if (words.length === 1) {
            return words[0][0].toUpperCase();
        } else {
            return (
                words[0][0].toUpperCase() + words[words.length - 1][0].toUpperCase()
            );
        }
    }

    if (image === null) {
        return (
            <span className="min-h-48 w-full  bg-blue-two p-2 border-2 border-white rounded-full text-lg">{getInitials(name)}</span>
        )
    }

    if (image != null) {
        let tranformation = detail ? "c_fill,h_400,w_400" : "c_fill,h_200,w_200"
        return (
            <img src={getThumbnailUrl(image, tranformation)} alt={alt} className={className || defaultStyles} />
        )
    }


}